<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\BulkSaleBatch;
use App\Models\Sales\BulkSaleItem;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class BulkSaleService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Create a new bulk sale batch.
     */
    public function createBatch(array $data, int $userId): BulkSaleBatch
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['status'] = BulkSaleBatch::STATUS_DRAFT;

            if (empty($data['batch_number'])) {
                $data['batch_number'] = $this->numberGenerator->generate('BSB');
            }

            // If sale_date differs from today, track original date
            if (isset($data['sale_date']) && $data['sale_date'] !== now()->toDateString()) {
                $data['original_sale_date'] = $data['sale_date'];
            }

            return BulkSaleBatch::create($data);
        });
    }

    /**
     * Add items to a bulk sale batch.
     */
    public function addItems(BulkSaleBatch $batch, array $items): BulkSaleBatch
    {
        if (!$batch->isEditable()) {
            throw new \InvalidArgumentException('Cannot add items to a non-draft batch.');
        }

        return DB::transaction(function () use ($batch, $items) {
            $lastLineNumber = $batch->items()->max('line_number') ?? 0;

            foreach ($items as $index => $itemData) {
                $lineNumber = $lastLineNumber + $index + 1;

                $subtotal = bcmul((string) $itemData['quantity'], (string) $itemData['unit_price'], 4);
                $discountAmount = $itemData['discount_amount'] ?? 0;
                $taxRate = $itemData['tax_rate'] ?? 0;
                $taxableAmount = bcsub($subtotal, (string) $discountAmount, 4);
                $taxAmount = bcmul($taxableAmount, bcdiv((string) $taxRate, '100', 6), 4);
                $totalAmount = bcadd($taxableAmount, $taxAmount, 4);

                BulkSaleItem::create(array_merge($itemData, [
                    'batch_id' => $batch->id,
                    'line_number' => $lineNumber,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'status' => BulkSaleItem::STATUS_PENDING,
                ]));
            }

            $batch->recalculateTotals();

            return $batch->fresh(['items']);
        });
    }

    /**
     * Process a bulk sale batch.
     */
    public function processBatch(BulkSaleBatch $batch): BulkSaleBatch
    {
        if (!$batch->canBeProcessed()) {
            throw new \InvalidArgumentException('Batch cannot be processed in its current state.');
        }

        return DB::transaction(function () use ($batch) {
            $batch->update([
                'status' => BulkSaleBatch::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($batch->items()->pending()->get() as $item) {
                try {
                    $item->update(['status' => BulkSaleItem::STATUS_PROCESSING]);

                    // Process each item - create invoice, record payment, etc.
                    // This is where the actual sales processing would happen
                    $item->update([
                        'status' => BulkSaleItem::STATUS_COMPLETED,
                        'processed_at' => now(),
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $item->update([
                        'status' => BulkSaleItem::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                        'processed_at' => now(),
                    ]);

                    $failedCount++;
                    $errors[] = [
                        'line_number' => $item->line_number,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $totalItems = $batch->items()->count();
            $status = match (true) {
                $failedCount === 0 => BulkSaleBatch::STATUS_COMPLETED,
                $successCount === 0 => BulkSaleBatch::STATUS_FAILED,
                default => BulkSaleBatch::STATUS_PARTIALLY_COMPLETED,
            };

            $batch->update([
                'status' => $status,
                'processed_count' => $successCount + $failedCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'errors' => !empty($errors) ? $errors : null,
                'completed_at' => now(),
            ]);

            return $batch->fresh(['items']);
        });
    }

    /**
     * Cancel a batch.
     */
    public function cancelBatch(BulkSaleBatch $batch, ?string $reason = null): BulkSaleBatch
    {
        if (!$batch->canBeCancelled()) {
            throw new \InvalidArgumentException('Batch cannot be cancelled in its current state.');
        }

        return DB::transaction(function () use ($batch, $reason) {
            // Cancel all pending items
            $batch->items()
                ->whereIn('status', [BulkSaleItem::STATUS_PENDING, BulkSaleItem::STATUS_PROCESSING])
                ->update(['status' => BulkSaleItem::STATUS_SKIPPED]);

            $batch->update([
                'status' => BulkSaleBatch::STATUS_FAILED,
                'notes' => $batch->notes ? $batch->notes . "\n\nCancelled: " . $reason : "Cancelled: " . $reason,
                'completed_at' => now(),
            ]);

            return $batch->fresh(['items']);
        });
    }

    /**
     * Get batch statistics.
     */
    public function getStats(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = BulkSaleBatch::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($fromDate) {
            $query->where('sale_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('sale_date', '<=', $toDate);
        }

        return [
            'total_batches' => (clone $query)->count(),
            'draft_batches' => (clone $query)->where('status', BulkSaleBatch::STATUS_DRAFT)->count(),
            'completed_batches' => (clone $query)->whereIn('status', [BulkSaleBatch::STATUS_COMPLETED, BulkSaleBatch::STATUS_PARTIALLY_COMPLETED])->count(),
            'failed_batches' => (clone $query)->where('status', BulkSaleBatch::STATUS_FAILED)->count(),
            'total_amount' => (clone $query)->whereIn('status', [BulkSaleBatch::STATUS_COMPLETED, BulkSaleBatch::STATUS_PARTIALLY_COMPLETED])->sum('total_amount'),
            'total_items_processed' => (clone $query)->sum('success_count'),
            'total_items_failed' => (clone $query)->sum('failed_count'),
            'by_status' => BulkSaleBatch::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($fromDate, fn ($q) => $q->where('sale_date', '>=', $fromDate))
                ->when($toDate, fn ($q) => $q->where('sale_date', '<=', $toDate))
                ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
        ];
    }
}
