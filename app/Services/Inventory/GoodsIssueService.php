<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Accounting\Account;
use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\GoodsIssueLine;
use App\Models\Inventory\StockMovement;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsIssueService
{
    public function __construct(
        private StockService $stockService,
        private JournalService $journalService,
        private NumberGeneratorService $numberGenerator
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a Goods Issue document in draft status.
     *
     * @param  array{
     *     warehouse_id: int,
     *     gi_date: string,
     *     movement_type: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     branch_id?: int|null,
     *     notes?: string|null,
     *     lines: array<array{
     *         product_id: int,
     *         variant_id?: int|null,
     *         warehouse_id?: int|null,
     *         location_id?: int|null,
     *         batch_id?: int|null,
     *         unit_id?: int|null,
     *         quantity: float|string,
     *         unit_cost?: float|string,
     *         serial_number?: string|null,
     *         notes?: string|null,
     *     }>
     * } $data
     */
    public function create(array $data, int $userId): GoodsIssue
    {
        return DB::transaction(function () use ($data, $userId): GoodsIssue {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if (empty($data['gi_number'])) {
                $data['gi_number'] = $this->numberGenerator->generate('GI');
            }

            $data['created_by'] = $userId;
            $data['status']     = GoodsIssue::STATUS_DRAFT;
            $data['gi_date']    = $data['gi_date'] ?? now()->toDateString();

            $gi = GoodsIssue::create($data);

            foreach ($lines as $lineData) {
                $this->createLine($gi, $lineData);
            }

            $gi->recalculateTotals();

            return $gi->load(['lines.product', 'lines.variant', 'lines.unit', 'warehouse']);
        });
    }

    /**
     * Update a Goods Issue that is still in draft status.
     *
     * Pass null for $lines to leave existing lines unchanged.
     */
    public function update(GoodsIssue $gi, array $data, ?array $lines = null): GoodsIssue
    {
        if (!$gi->isDraft()) {
            throw new \InvalidArgumentException('Only draft Goods Issues can be updated.');
        }

        return DB::transaction(function () use ($gi, $data, $lines): GoodsIssue {
            // Prevent status from being changed via this method.
            unset($data['status'], $data['gi_number']);

            $gi->update($data);

            if ($lines !== null) {
                $gi->lines()->delete();

                foreach ($lines as $lineData) {
                    $this->createLine($gi, $lineData);
                }
            }

            $gi->recalculateTotals();

            return $gi->fresh(['lines.product', 'lines.variant', 'lines.unit', 'warehouse']);
        });
    }

    /**
     * Post a draft Goods Issue:
     *  1. Deduct stock for every line via StockService::recordMovement().
     *  2. Create a GL journal entry (COGS/expense debit, Inventory credit).
     *  3. Transition status to `posted`.
     */
    public function post(GoodsIssue $gi, int $userId): GoodsIssue
    {
        if (!$gi->canBePosted()) {
            throw new \InvalidArgumentException(
                'Only draft Goods Issues with at least one line can be posted.'
            );
        }

        return DB::transaction(function () use ($gi, $userId): GoodsIssue {
            // Re-fetch with a pessimistic lock to serialise concurrent posts.
            $gi = GoodsIssue::lockForUpdate()->with(['lines.product'])->findOrFail($gi->id);

            foreach ($gi->lines as $line) {
                if ($line->product_id && $line->product?->track_inventory) {
                    $this->stockService->recordMovement(
                        productId:     $line->product_id,
                        warehouseId:   $line->warehouse_id ?? $gi->warehouse_id,
                        movementType:  StockMovement::TYPE_MATERIAL_ISSUE,
                        direction:     StockMovement::DIRECTION_OUT,
                        quantity:      (float) $line->quantity,
                        unitCost:      (float) $line->unit_cost,
                        variantId:     $line->variant_id,
                        locationId:    $line->location_id,
                        referenceType: GoodsIssue::class,
                        referenceId:   $gi->id,
                        referenceNumber: $gi->gi_number,
                        notes:         $line->notes,
                        createdBy:     $userId
                    );
                }
            }

            $journalEntryId = $this->createGlJournalEntry($gi);

            $gi->update([
                'status'           => GoodsIssue::STATUS_POSTED,
                'posted_by'        => $userId,
                'posted_at'        => now(),
                'journal_entry_id' => $journalEntryId,
            ]);

            return $gi->fresh(['lines', 'journalEntry', 'postedBy']);
        });
    }

    /**
     * Reverse a posted Goods Issue:
     *  1. Restore stock for every line (IN movement).
     *  2. Reverse the GL journal entry.
     *  3. Transition status to `reversed`.
     */
    public function reverse(GoodsIssue $gi, string $reason, int $userId): GoodsIssue
    {
        if (!$gi->canBeReversed()) {
            throw new \InvalidArgumentException('Only posted Goods Issues can be reversed.');
        }

        return DB::transaction(function () use ($gi, $reason, $userId): GoodsIssue {
            $gi = GoodsIssue::lockForUpdate()->with(['lines.product'])->findOrFail($gi->id);

            // Restore inventory for each line.
            foreach ($gi->lines as $line) {
                if ($line->product_id && $line->product?->track_inventory) {
                    $this->stockService->recordMovement(
                        productId:     $line->product_id,
                        warehouseId:   $line->warehouse_id ?? $gi->warehouse_id,
                        movementType:  StockMovement::TYPE_ADJUSTMENT,
                        direction:     StockMovement::DIRECTION_IN,
                        quantity:      (float) $line->quantity,
                        unitCost:      (float) $line->unit_cost,
                        variantId:     $line->variant_id,
                        locationId:    $line->location_id,
                        referenceType: GoodsIssue::class,
                        referenceId:   $gi->id,
                        referenceNumber: $gi->gi_number,
                        notes:         "Reversal of GI {$gi->gi_number}: {$reason}",
                        createdBy:     $userId
                    );
                }
            }

            // Reverse the GL journal entry if one was created.
            if ($gi->journal_entry_id && $gi->journalEntry) {
                try {
                    $this->journalService->void($gi->journalEntry, $reason);
                } catch (\Throwable $e) {
                    Log::warning('GI journal reversal failed — continuing', [
                        'gi_id' => $gi->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $gi->update([
                'status'          => GoodsIssue::STATUS_REVERSED,
                'reversed_by'     => $userId,
                'reversed_at'     => now(),
                'reversal_reason' => $reason,
            ]);

            return $gi->fresh(['lines', 'reversedBy']);
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Create a single GoodsIssueLine and compute its total_value.
     */
    private function createLine(GoodsIssue $gi, array $lineData): GoodsIssueLine
    {
        $quantity  = (float) ($lineData['quantity'] ?? 0);
        $unitCost  = (float) ($lineData['unit_cost'] ?? 0);
        $totalValue = bcmul((string) $quantity, (string) $unitCost, 4);

        $lineData['goods_issue_id'] = $gi->id;
        $lineData['warehouse_id']   = $lineData['warehouse_id'] ?? $gi->warehouse_id;
        $lineData['total_value']    = $totalValue;

        return GoodsIssueLine::create($lineData);
    }

    /**
     * Create GL journal entry for a Goods Issue posting.
     *
     * Accounting treatment:
     *   Debit  COGS / Expense account   (the goods have been consumed)
     *   Credit Inventory asset account  (inventory leaves the balance sheet)
     *
     * If either account is not configured the entry is skipped (non-fatal) and
     * null is returned.
     */
    private function createGlJournalEntry(GoodsIssue $gi): ?int
    {
        $totalValue = (float) $gi->lines()->sum('total_value');

        if ($totalValue <= 0) {
            return null;
        }

        $inventoryAccount = Account::where('organization_id', $gi->organization_id)
            ->where('account_type', 'asset')
            ->where(function ($q): void {
                $q->where('name', 'like', '%Inventory%')
                    ->orWhere('name', 'like', '%Stock%');
            })
            ->first();

        $cogsAccount = Account::where('organization_id', $gi->organization_id)
            ->where('account_type', 'expense')
            ->where(function ($q): void {
                $q->where('name', 'like', '%Cost of%')
                    ->orWhere('name', 'like', '%COGS%')
                    ->orWhere('name', 'like', '%Cost of Goods%');
            })
            ->first();

        if (!$inventoryAccount || !$cogsAccount) {
            Log::info('GI journal entry skipped: inventory or COGS account not configured', [
                'gi_id' => $gi->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createEntry([
            'organization_id' => $gi->organization_id,
            'entry_date'      => $gi->gi_date->toDateString(),
            'reference'       => $gi->gi_number,
            'description'     => "Goods Issue {$gi->gi_number} — {$gi->getMovementTypeLabel()}",
            'status'          => 'posted',
        ], [
            // Debit: COGS / Expense
            [
                'account_id'  => $cogsAccount->id,
                'description' => "Goods issue — {$gi->gi_number}",
                'debit'       => $totalValue,
                'credit'      => 0,
            ],
            // Credit: Inventory asset
            [
                'account_id'  => $inventoryAccount->id,
                'description' => "Inventory reduction — {$gi->gi_number}",
                'debit'       => 0,
                'credit'      => $totalValue,
            ],
        ]);

        return $entry?->id;
    }
}
