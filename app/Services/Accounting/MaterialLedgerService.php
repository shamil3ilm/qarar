<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\MlClosingEntry;
use App\Models\Accounting\MlDocument;
use App\Models\Accounting\MlPriceDifference;
use App\Models\Accounting\MaterialLedgerRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaterialLedgerService
{
    /**
     * Paginate material ledger records with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = MaterialLedgerRecord::with(['product:id,name,sku', 'warehouse:id,name'])
            ->orderByDesc('fiscal_year')
            ->orderByDesc('period');

        if (!empty($filters['period'])) {
            $query->where('period', (int) $filters['period']);
        }

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Get records for a specific product, newest period first.
     */
    public function getProductRecords(int $productId, array $filters): LengthAwarePaginator
    {
        $query = MaterialLedgerRecord::with(['warehouse:id,name'])
            ->forProduct($productId)
            ->orderByDesc('fiscal_year')
            ->orderByDesc('period');

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Get or create a material ledger record for the given product / period combination.
     */
    public function getOrCreateRecord(
        int $productId,
        ?int $warehouseId,
        int $period,
        int $year
    ): MaterialLedgerRecord {
        return MaterialLedgerRecord::firstOrCreate(
            [
                'organization_id' => auth()->user()?->organization_id,
                'product_id'      => $productId,
                'warehouse_id'    => $warehouseId,
                'period'          => $period,
                'fiscal_year'     => $year,
            ],
            [
                'status'         => MaterialLedgerRecord::STATUS_OPEN,
                'currency_code'  => 'SAR',
                'price_unit'     => 1,
            ]
        );
    }

    /**
     * Post a document (goods movement, invoice, etc.) to a material ledger record.
     * Updates cumulative totals on the record in the same transaction.
     */
    public function postDocument(MaterialLedgerRecord $record, array $documentData): MlDocument
    {
        if ($record->status === MaterialLedgerRecord::STATUS_CLOSED) {
            throw new RuntimeException('Cannot post to a closed material ledger record.');
        }

        return DB::transaction(function () use ($record, $documentData): MlDocument {
            $document = MlDocument::create([
                'organization_id'           => $record->organization_id,
                'material_ledger_record_id' => $record->id,
                'document_type'             => $documentData['document_type'],
                'reference_type'            => $documentData['reference_type'] ?? null,
                'reference_id'              => $documentData['reference_id'] ?? null,
                'quantity'                  => $documentData['quantity'],
                'standard_value'            => $documentData['standard_value'],
                'actual_value'              => $documentData['actual_value'] ?? null,
                'price_difference'          => $documentData['price_difference'] ?? 0,
                'posting_date'              => $documentData['posting_date'],
            ]);

            $quantity      = (float) $documentData['quantity'];
            $standardValue = (float) $documentData['standard_value'];

            $isReceipt = in_array($documentData['document_type'], [
                MlDocument::TYPE_GOODS_RECEIPT,
                MlDocument::TYPE_INVOICE,
            ], true);

            if ($isReceipt) {
                $record->increment('cumulative_receipts_qty', $quantity);
                $record->increment('cumulative_receipts_value', $standardValue);
            } else {
                $record->increment('cumulative_issues_qty', $quantity);
                $record->increment('cumulative_issues_value', $standardValue);
            }

            return $document;
        });
    }

    /**
     * Run period close for all open records in the given period/year.
     * Calculates actual prices and creates closing entries.
     */
    public function runPeriodClose(int $period, int $year, int $orgId): array
    {
        $records = MaterialLedgerRecord::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('period', $period)
            ->where('fiscal_year', $year)
            ->where('status', MaterialLedgerRecord::STATUS_OPEN)
            ->get();

        if ($records->isEmpty()) {
            return ['closed' => 0, 'message' => 'No open records found for the specified period.'];
        }

        $closedCount = 0;

        DB::transaction(function () use ($records, $period, $year, &$closedCount): void {
            foreach ($records as $record) {
                $actualPrice = $record->getActualCost();
                $standardPrice = (float) $record->standard_price;
                $priceDiff = round($actualPrice - $standardPrice, 4);

                $closingQty = (float) $record->opening_stock_qty
                    + (float) $record->cumulative_receipts_qty
                    - (float) $record->cumulative_issues_qty;

                $closingValue = round($closingQty * ($actualPrice / max(1, (int) $record->price_unit)), 4);

                $entry = MlClosingEntry::create([
                    'organization_id'           => $record->organization_id,
                    'material_ledger_record_id' => $record->id,
                    'period'                    => $period,
                    'fiscal_year'               => $year,
                    'total_price_difference'    => $priceDiff * $closingQty,
                    'revaluation_amount'        => round($priceDiff * $closingQty, 4),
                    'actual_price_calculated'   => $actualPrice,
                    'run_by'                    => auth()->id(),
                    'run_at'                    => now(),
                ]);

                $this->revalueInventory($entry);

                $record->update([
                    'actual_price'      => $actualPrice,
                    'price_difference'  => $priceDiff,
                    'closing_stock_qty' => $closingQty,
                    'closing_stock_value' => $closingValue,
                    'status'            => MaterialLedgerRecord::STATUS_CLOSED,
                    'closed_at'         => now(),
                ]);

                $closedCount++;
            }
        });

        return [
            'closed'  => $closedCount,
            'period'  => $period,
            'year'    => $year,
            'message' => "Period close completed. {$closedCount} record(s) closed.",
        ];
    }

    /**
     * Create price difference breakdown entries for a closing entry.
     */
    public function revalueInventory(MlClosingEntry $entry): void
    {
        $record = $entry->materialLedgerRecord;

        if (!$record) {
            return;
        }

        $totalDiff = (float) $entry->total_price_difference;

        if (abs($totalDiff) < 0.0001) {
            return;
        }

        // Post the full difference as a purchase price variance by default.
        // In a full implementation this would be split across categories
        // based on invoice matching and exchange rate data.
        MlPriceDifference::create([
            'organization_id'      => $entry->organization_id,
            'ml_closing_entry_id'  => $entry->id,
            'product_id'           => $record->product_id,
            'category'             => MlPriceDifference::CATEGORY_PURCHASE_PRICE_VARIANCE,
            'amount'               => $totalDiff,
            'quantity_affected'    => $record->closing_stock_qty,
        ]);
    }

    /**
     * Build a summary report for a given period.
     */
    public function getPeriodReport(int $period, int $year): array
    {
        $records = MaterialLedgerRecord::with(['product:id,name,sku', 'warehouse:id,name'])
            ->forPeriod($period, $year)
            ->get();

        $totalStandardValue = $records->sum(fn ($r) => (float) $r->opening_stock_value + (float) $r->cumulative_receipts_value);
        $totalActualValue   = $records->sum(fn ($r) => $r->getStockValue());

        return [
            'period'                => $period,
            'fiscal_year'           => $year,
            'total_records'         => $records->count(),
            'open_records'          => $records->where('status', MaterialLedgerRecord::STATUS_OPEN)->count(),
            'closed_records'        => $records->where('status', MaterialLedgerRecord::STATUS_CLOSED)->count(),
            'total_standard_value'  => round($totalStandardValue, 4),
            'total_actual_value'    => round($totalActualValue, 4),
            'total_price_diff'      => round($totalActualValue - $totalStandardValue, 4),
            'records'               => $records->values(),
        ];
    }

    /**
     * Paginate closing entries with optional period/year filter.
     */
    public function getClosingEntries(array $filters): LengthAwarePaginator
    {
        $query = MlClosingEntry::with([
            'materialLedgerRecord.product:id,name,sku',
            'runBy:id,name',
        ])->orderByDesc('run_at');

        if (!empty($filters['period'])) {
            $query->where('period', (int) $filters['period']);
        }

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', (int) $filters['fiscal_year']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }
}
