<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\PhysicalInventoryDocument;
use App\Models\Inventory\PhysicalInventoryLine;
use App\Models\Inventory\StockLevel;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhysicalInventoryService
{
    public function __construct(
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator,
        private JournalService $journalService
    ) {}

    /**
     * Create a physical inventory document and auto-populate lines with current book quantities.
     */
    public function createDocument(array $data): PhysicalInventoryDocument
    {
        return DB::transaction(function () use ($data): PhysicalInventoryDocument {
            $data['organization_id'] = auth()->user()->organization_id;

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->numberGenerator->generate('PI');
            }

            $data['status'] = PhysicalInventoryDocument::STATUS_CREATED;

            $document = PhysicalInventoryDocument::create($data);

            // Auto-populate lines from current stock levels in the warehouse
            $stockLevels = StockLevel::where('warehouse_id', $document->warehouse_id)
                ->with(['product', 'variant'])
                ->get();

            foreach ($stockLevels as $level) {
                $document->lines()->create([
                    'product_id' => $level->product_id,
                    'variant_id' => $level->variant_id,
                    'warehouse_location_id' => $level->location_id,
                    'book_quantity' => $level->quantity,
                    'unit_cost' => $level->average_cost,
                    'counted_quantity' => null,
                    'difference_quantity' => null,
                    'difference_value' => null,
                    'adjustment_status' => 'pending',
                ]);
            }

            return $document->load(['lines.product', 'lines.variant', 'warehouse']);
        });
    }

    /**
     * Enter counted quantities for lines, calculating differences automatically.
     *
     * @param  array<int, array{line_id: int, counted_quantity: float}>  $lines
     */
    public function enterCounts(PhysicalInventoryDocument $document, array $lines): PhysicalInventoryDocument
    {
        if (!$document->canEnterCounts()) {
            throw new \InvalidArgumentException('Counts can only be entered for documents in created or in_progress status.');
        }

        DB::transaction(function () use ($document, $lines): void {
            foreach ($lines as $lineData) {
                /** @var PhysicalInventoryLine|null $line */
                $line = $document->lines()->find($lineData['line_id']);

                if ($line === null) {
                    continue;
                }

                $counted = (float) $lineData['counted_quantity'];
                $diff = (float) bcsub((string) $counted, (string) $line->book_quantity, 4);
                $diffValue = $line->unit_cost !== null
                    ? (float) bcmul((string) $diff, (string) $line->unit_cost, 4)
                    : null;

                $line->update([
                    'counted_quantity' => $counted,
                    'difference_quantity' => $diff,
                    'difference_value' => $diffValue,
                ]);
            }

            // Transition to in_progress if still at created
            if ($document->status === PhysicalInventoryDocument::STATUS_CREATED) {
                $document->update(['status' => PhysicalInventoryDocument::STATUS_IN_PROGRESS]);
            }

            // If all lines are counted, move to counted status
            $uncounted = $document->lines()->whereNull('counted_quantity')->count();
            if ($uncounted === 0) {
                $document->update([
                    'status' => PhysicalInventoryDocument::STATUS_COUNTED,
                    'counted_at' => now(),
                ]);
            }
        });

        return $document->fresh(['lines.product', 'lines.variant', 'warehouse']);
    }

    /**
     * Post adjustments: create stock adjustments for all lines with differences,
     * mark the inventory document as posted, and create journal entries for value differences.
     */
    public function postAdjustments(PhysicalInventoryDocument $document): PhysicalInventoryDocument
    {
        if (!$document->canPost()) {
            throw new \InvalidArgumentException('Document cannot be posted in its current status.');
        }

        return DB::transaction(function () use ($document): PhysicalInventoryDocument {
            $linesWithDiffs = $document->lines()
                ->pending()
                ->whereNotNull('difference_quantity')
                ->where('difference_quantity', '!=', 0)
                ->with(['product', 'variant'])
                ->get();

            if ($linesWithDiffs->isEmpty()) {
                // Nothing to adjust — just post the document
                $document->update([
                    'status' => PhysicalInventoryDocument::STATUS_POSTED,
                    'posted_by' => auth()->id(),
                    'posted_at' => now(),
                ]);

                return $document->fresh(['lines.product', 'warehouse']);
            }

            // Create stock adjustments
            $adjustmentNumber = $this->numberGenerator->generate('ADJ');

            $adjustment = \App\Models\Inventory\StockAdjustment::create([
                'organization_id' => $document->organization_id,
                'warehouse_id' => $document->warehouse_id,
                'adjustment_number' => $adjustmentNumber,
                'adjustment_date' => now()->toDateString(),
                'reason' => \App\Models\Inventory\StockAdjustment::REASON_COUNT_CORRECTION,
                'notes' => "Physical inventory count: {$document->document_number}",
                'created_by' => auth()->id(),
            ]);

            foreach ($linesWithDiffs as $line) {
                $newQuantity = (float) $line->counted_quantity;

                // Create adjustment line
                $adjustment->lines()->create([
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'location_id' => $line->warehouse_location_id,
                    'system_quantity' => $line->book_quantity,
                    'actual_quantity' => $newQuantity,
                    'difference' => $line->difference_quantity,
                    'unit_cost' => $line->unit_cost ?? 0,
                    'total_cost' => $line->difference_value ?? 0,
                ]);

                // Update stock level
                $this->stockService->adjust(
                    productId: $line->product_id,
                    warehouseId: $document->warehouse_id,
                    newQuantity: $newQuantity,
                    variantId: $line->variant_id,
                    locationId: $line->warehouse_location_id,
                    referenceNumber: $document->document_number,
                    referenceId: $document->id,
                    notes: "Physical inventory adjustment: {$document->document_number}"
                );

                // Mark inventory line as adjusted
                $line->update(['adjustment_status' => 'adjusted']);
            }

            // Post the adjustment
            $adjustment->update([
                'status' => \App\Models\Inventory\StockAdjustment::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            // Attempt to create a journal entry for total inventory value variance
            $totalVariance = $linesWithDiffs->sum('difference_value');
            if ($totalVariance != 0) {
                try {
                    $this->journalService->createInventoryAdjustmentEntry(
                        $document->organization_id,
                        $document->document_number,
                        (float) $totalVariance,
                        now()
                    );
                } catch (\Throwable $e) {
                    Log::warning('Could not create journal entry for physical inventory adjustment', [
                        'document_number' => $document->document_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $document->update([
                'status' => PhysicalInventoryDocument::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $document->fresh(['lines.product', 'lines.variant', 'warehouse']);
        });
    }
}
