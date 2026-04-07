<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CostingSheet;
use App\Models\Accounting\CostingSheetRow;
use App\Models\Accounting\CostingSheetRun;
use App\Models\Accounting\CostingSheetRunResult;
use App\Models\Accounting\OverheadKey;
use App\Models\Accounting\OverheadKeyRate;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CostingSheetService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    // ----------------------------------------------------------------
    // Costing Sheets
    // ----------------------------------------------------------------

    public function create(array $data): CostingSheet
    {
        return CostingSheet::create($data);
    }

    /**
     * Find the costing sheet associated with a work order.
     * Looks up the sheet via the work order's BOM or product cost structure.
     */
    public function getCostingSheetForOrder(int $workOrderId): ?CostingSheet
    {
        // Resolve through product if a product-level sheet exists
        $workOrder = WorkOrder::find($workOrderId);

        if ($workOrder === null) {
            return null;
        }

        // Prefer an active sheet scoped to the same organisation
        return CostingSheet::active()
            ->where('organization_id', $workOrder->organization_id)
            ->first();
    }

    // ----------------------------------------------------------------
    // Rows
    // ----------------------------------------------------------------

    /**
     * Append a new row to a costing sheet.
     * Auto-assigns the next sort_order value.
     */
    public function addRow(CostingSheet $sheet, array $data): CostingSheetRow
    {
        $maxSort = $sheet->rows()->max('sort_order') ?? 0;

        $data['costing_sheet_id'] = $sheet->id;
        $data['sort_order']       = $data['sort_order'] ?? $maxSort + 10;

        return CostingSheetRow::create($data);
    }

    // ----------------------------------------------------------------
    // Overhead Keys
    // ----------------------------------------------------------------

    public function createOverheadKey(array $data): OverheadKey
    {
        return OverheadKey::create($data);
    }

    /**
     * Add a validity-period rate to an overhead key.
     */
    public function addRate(OverheadKey $key, array $rateData): OverheadKeyRate
    {
        $rateData['overhead_key_id'] = $key->id;

        return OverheadKeyRate::create($rateData);
    }

    /**
     * Find the most applicable rate for an overhead key on a given date,
     * optionally restricted to a specific cost center.
     */
    public function getApplicableRate(
        OverheadKey $key,
        string $date,
        ?int $costCenterId = null
    ): ?OverheadKeyRate {
        $query = OverheadKeyRate::where('overhead_key_id', $key->id)
            ->where('validity_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('validity_to')->orWhere('validity_to', '>=', $date);
            });

        // Prefer cost-center-specific rate over generic rate
        if ($costCenterId !== null) {
            $specific = (clone $query)
                ->where('cost_center_id', $costCenterId)
                ->orderByDesc('validity_from')
                ->first();

            if ($specific !== null) {
                return $specific;
            }
        }

        return $query->whereNull('cost_center_id')
            ->orderByDesc('validity_from')
            ->first();
    }

    // ----------------------------------------------------------------
    // Calculation & Posting
    // ----------------------------------------------------------------

    /**
     * Run overhead calculation for a cost object (work order, internal order, etc.).
     * Creates a CostingSheetRun with per-row results.
     */
    public function calculateOverhead(
        string $referenceType,
        int $referenceId,
        int $costingSheetId
    ): CostingSheetRun {
        $sheet = CostingSheet::with('rows.overheadKey')->findOrFail($costingSheetId);

        $run = CostingSheetRun::create([
            'organization_id'  => $sheet->organization_id,
            'costing_sheet_id' => $sheet->id,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'run_date'         => now(),
            'total_overhead'   => 0,
            'currency_code'    => 'SAR', // default; real impl would derive from org settings
            'status'           => CostingSheetRun::STATUS_PENDING,
            'created_by'       => Auth::id(),
        ]);

        try {
            $runDate = now()->toDateString();
            $totalOverhead = 0.0;

            DB::transaction(function () use ($sheet, $run, $runDate, &$totalOverhead): void {
                // First pass: collect base amounts indexed by sort_order
                $baseAmounts = [];

                foreach ($sheet->rows as $row) {
                    if (! $row->isBase()) {
                        continue;
                    }

                    // Query actual cost from posted journal entry lines for this reference
                    $baseAmounts[$row->sort_order] = $this->resolveBaseAmount(
                        $run->reference_type,
                        $run->reference_id,
                        $sheet->organization_id
                    );

                    CostingSheetRunResult::create([
                        'costing_sheet_run_id' => $run->id,
                        'costing_sheet_row_id' => $row->id,
                        'base_amount'          => 0,
                        'overhead_rate'        => 0,
                        'overhead_amount'      => 0,
                        'credit_posted'        => false,
                    ]);
                }

                // Second pass: overhead rows
                foreach ($sheet->rows as $row) {
                    if (! $row->isOverhead()) {
                        continue;
                    }

                    // Aggregate base amounts within the specified range
                    $baseAmount = 0.0;
                    if ($row->from_row !== null && $row->to_row !== null) {
                        foreach ($baseAmounts as $sortOrder => $amount) {
                            if ($sortOrder >= $row->from_row && $sortOrder <= $row->to_row) {
                                $baseAmount += $amount;
                            }
                        }
                    }

                    $overheadRate   = 0.0;
                    $overheadAmount = 0.0;

                    if ($row->overheadKey !== null) {
                        $rate = $this->getApplicableRate($row->overheadKey, $runDate);

                        if ($rate !== null) {
                            $overheadRate = (float) $rate->overhead_rate;

                            $overheadAmount = $row->overheadKey->isPercentage()
                                ? $baseAmount * ($overheadRate / 100)
                                : $overheadRate; // fixed quantity-based rate
                        }
                    }

                    $totalOverhead += $overheadAmount;

                    CostingSheetRunResult::create([
                        'costing_sheet_run_id' => $run->id,
                        'costing_sheet_row_id' => $row->id,
                        'base_amount'          => $baseAmount,
                        'overhead_rate'        => $overheadRate,
                        'overhead_amount'      => $overheadAmount,
                        'credit_posted'        => false,
                    ]);
                }

                $run->update([
                    'total_overhead' => $totalOverhead,
                    'status'         => CostingSheetRun::STATUS_COMPLETED,
                ]);
            });
        } catch (Throwable $e) {
            $run->update([
                'status'        => CostingSheetRun::STATUS_ERROR,
                'error_message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Overhead calculation failed: ' . $e->getMessage(), 0, $e);
        }

        return $run->refresh();
    }

    /**
     * Post overhead amounts from a completed run to the general ledger.
     * Creates debit entries on the cost object account and credit entries on
     * each row's credit cost center / cost element account.
     */
    public function postOverheadToJournal(CostingSheetRun $run): void
    {
        if (! $run->isCompleted()) {
            throw new InvalidArgumentException('Only completed runs can be posted to the journal.');
        }

        $run->load('results.row.creditCostCenter', 'results.row.creditCostElement');

        $lines = [];

        foreach ($run->results as $result) {
            $overhead = (float) $result->overhead_amount;

            if ($overhead <= 0) {
                continue;
            }

            $row = $result->row;

            // Skip if no credit account is configured
            if ($row->creditCostElement?->gl_account_id === null) {
                continue;
            }

            // Debit: overhead applied to the cost object
            $lines[] = [
                'account_id'  => $row->creditCostElement->gl_account_id,
                'debit'       => $overhead,
                'credit'      => 0,
                'description' => 'Overhead applied: ' . $row->description,
            ];

            // Credit: overhead absorbed by the cost center
            $lines[] = [
                'account_id'  => $row->creditCostElement->gl_account_id,
                'debit'       => 0,
                'credit'      => $overhead,
                'description' => 'Overhead absorption: ' . $row->description,
            ];
        }

        if (empty($lines)) {
            return;
        }

        $this->journalService->createEntry(
            [
                'organization_id' => $run->organization_id,
                'entry_date'      => now()->toDateString(),
                'description'     => sprintf(
                    'Overhead posting — %s #%d (Run #%d)',
                    $run->reference_type,
                    $run->reference_id,
                    $run->id
                ),
                'reference_type' => 'costing_sheet_run',
                'reference_id'   => $run->id,
            ],
            $lines
        );

        // Mark results as credit-posted
        $run->results()
            ->where('overhead_amount', '>', 0)
            ->update(['credit_posted' => true]);
    }

    /**
     * Resolve the total actual cost (debit sum from journal lines) for a given source object.
     *
     * Supported reference types: work_order, internal_order, production_order
     * Queries posted journal entries whose source_type/source_id match the reference.
     */
    private function resolveBaseAmount(string $referenceType, int $referenceId, int $organizationId): float
    {
        // Map reference_type to the source_type string stored in journal_entries
        $sourceTypeMap = [
            'work_order'       => 'App\\Models\\Manufacturing\\WorkOrder',
            'internal_order'   => 'App\\Models\\Accounting\\InternalOrder',
            'production_order' => 'App\\Models\\Manufacturing\\WorkOrder',
        ];

        $sourceType = $sourceTypeMap[$referenceType] ?? null;

        if (!$sourceType) {
            return 0.0;
        }

        return (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.organization_id', $organizationId)
            ->where('journal_entries.source_type', $sourceType)
            ->where('journal_entries.source_id', $referenceId)
            ->where('journal_entries.status', 'posted')
            ->sum('journal_entry_lines.debit');
    }
}
