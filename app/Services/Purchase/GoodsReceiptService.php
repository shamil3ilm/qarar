<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\WarehouseTransferOrder;
use App\Models\Inventory\WarehouseTransferOrderItem;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\InspectionLotConfig;
use App\Models\Purchase\Bill;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\GoodsReceiptLine;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ThreeWayMatchResult;
use App\Models\Inventory\StockMovement;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\MaterialValuationService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsReceiptService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
        private JournalService $journalService,
        private MaterialValuationService $materialValuationService
    ) {}

    /**
     * Create a Goods Receipt (draft) against a Purchase Order.
     */
    public function createGr(PurchaseOrder $purchaseOrder, array $data): GoodsReceipt
    {
        if (!$purchaseOrder->canBeReceived()) {
            throw new \InvalidArgumentException('Purchase order cannot be received in its current status.');
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            if (empty($data['gr_number'])) {
                $data['gr_number'] = $this->numberGenerator->generate('GR');
            }

            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $data['purchase_order_id'] = $purchaseOrder->id;
            $data['contact_id'] = $data['contact_id'] ?? $purchaseOrder->supplier_id;
            $data['organization_id'] = $purchaseOrder->organization_id;
            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = GoodsReceipt::STATUS_DRAFT;
            $data['gr_date'] = $data['gr_date'] ?? now()->toDateString();

            $gr = GoodsReceipt::create($data);

            foreach ($lines as $lineData) {
                // Link to PO line for 3-way matching
                if (!empty($lineData['po_line_id'])) {
                    $poLine = $purchaseOrder->lines()->find($lineData['po_line_id']);

                    if ($poLine) {
                        $lineData['quantity_ordered'] = $lineData['quantity_ordered'] ?? $poLine->quantity;
                        $lineData['unit_cost'] = $lineData['unit_cost'] ?? $poLine->unit_price;
                        $lineData['product_id'] = $lineData['product_id'] ?? $poLine->product_id;
                        $lineData['unit_id'] = $lineData['unit_id'] ?? $poLine->unit_id;
                        $lineData['description'] = $lineData['description'] ?? $poLine->description;
                    }
                }

                $lineData['total_cost'] = bcmul(
                    (string) ($lineData['unit_cost'] ?? 0),
                    (string) ($lineData['quantity_received'] ?? 0),
                    4
                );

                $gr->lines()->create($lineData);
            }

            return $gr->load(['lines.product', 'lines.unit']);
        });
    }

    /**
     * Check whether any GR lines contain products that require quality inspection.
     * If so, create a single InspectionLot covering all inspectable lines,
     * put the GR into the `in_inspection` status, and return the new lot.
     * Returns null when no inspection is needed.
     */
    public function triggerInspectionIfRequired(GoodsReceipt $gr): ?InspectionLot
    {
        $gr->load(['lines.product']);

        // Collect lines where the product is flagged for inspection AND has
        // a matching InspectionLotConfig for the goods_receipt trigger.
        $inspectableLines = $gr->lines->filter(function (GoodsReceiptLine $line): bool {
            if (!$line->product) {
                return false;
            }

            if ($line->product->requires_inspection) {
                return true;
            }

            // Also honour InspectionLotConfig if the product doesn't carry the flag directly.
            return InspectionLotConfig::where('organization_id', $line->product->organization_id)
                ->where('product_id', $line->product_id)
                ->where('inspection_trigger', InspectionLotConfig::TRIGGER_GOODS_RECEIPT)
                ->where('auto_create', true)
                ->exists();
        });

        if ($inspectableLines->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($gr, $inspectableLines): InspectionLot {
            // Use the first inspectable line's product as the representative product.
            // For multi-product GRs a separate lot per product could be created; the
            // simplest SAP-style approach is one lot per GR document.
            $firstLine = $inspectableLines->first();
            $totalQty  = $inspectableLines->sum('quantity_received');

            // Look up any associated QualityPlan via InspectionLotConfig.
            $config = InspectionLotConfig::where('organization_id', $gr->organization_id)
                ->where('product_id', $firstLine->product_id)
                ->where('inspection_trigger', InspectionLotConfig::TRIGGER_GOODS_RECEIPT)
                ->first();

            $lot = InspectionLot::create([
                'organization_id'    => $gr->organization_id,
                'lot_number'         => 'LOT-GR-' . $gr->gr_number . '-' . now()->format('YmdHis'),
                'quality_plan_id'    => $config?->quality_plan_id,
                'product_id'         => $firstLine->product_id,
                'warehouse_id'       => $gr->warehouse_id,
                'source_type'        => InspectionLot::SOURCE_PURCHASE_ORDER,
                'source_id'          => $gr->id,
                'quantity'           => $totalQty,
                'inspected_quantity' => 0,
                'accepted_quantity'  => 0,
                'rejected_quantity'  => 0,
                'status'             => InspectionLot::STATUS_PENDING,
                'created_by'         => auth()->id(),
            ]);

            $gr->update([
                'status'             => GoodsReceipt::STATUS_IN_INSPECTION,
                'inspection_lot_id'  => $lot->id,
            ]);

            Log::info('GR placed in inspection', [
                'gr_id'  => $gr->id,
                'lot_id' => $lot->id,
            ]);

            return $lot;
        });
    }

    /**
     * Complete a QI gate: accept or reject quantities on the inspection lot and
     * transition the GR accordingly.
     *
     * - If accepted_quantity > 0  → the GR can be posted (call postGr() next).
     * - If rejected_quantity >= total quantity → the GR is rejected outright.
     * - Partial accept             → the GR lines are adjusted and can be posted.
     */
    public function resolveInspection(
        GoodsReceipt $gr,
        float $acceptedQuantity,
        float $rejectedQuantity,
        int $userId
    ): GoodsReceipt {
        if (!$gr->isInInspection()) {
            throw new \InvalidArgumentException('GR is not currently in inspection.');
        }

        if (!$gr->inspection_lot_id) {
            throw new \InvalidArgumentException('GR has no associated inspection lot.');
        }

        return DB::transaction(function () use ($gr, $acceptedQuantity, $rejectedQuantity, $userId): GoodsReceipt {
            $lot = InspectionLot::lockForUpdate()->findOrFail($gr->inspection_lot_id);

            // Complete the inspection lot (updates status internally).
            $lot->complete($acceptedQuantity, $rejectedQuantity, $userId);

            if ($lot->isRejected()) {
                // Full rejection — mark GR lines as rejected and set GR back to draft
                // so it can be reversed or deleted by the user.
                $gr->lines()->update(['quantity_rejected' => \Illuminate\Support\Facades\DB::raw('quantity_received')]);
                $gr->update(['status' => GoodsReceipt::STATUS_DRAFT]);

                Log::info('GR inspection fully rejected', ['gr_id' => $gr->id, 'lot_id' => $lot->id]);
            } else {
                // Partial or full acceptance — update line accepted quantities and
                // allow the GR to proceed to posting.  For simplicity we distribute
                // rejection evenly across lines; production systems may allow per-line
                // acceptance.
                $totalQty = (float) $gr->lines->sum('quantity_received');

                if ($totalQty > 0) {
                    $rejectionRatio = $rejectedQuantity / $totalQty;

                    foreach ($gr->lines as $line) {
                        $lineRejected = round((float) $line->quantity_received * $rejectionRatio, 4);
                        $line->update(['quantity_rejected' => $lineRejected]);
                    }
                }

                // Transition back to draft so postGr() can be called by the caller.
                $gr->update(['status' => GoodsReceipt::STATUS_DRAFT]);

                Log::info('GR inspection accepted — ready to post', [
                    'gr_id'    => $gr->id,
                    'accepted' => $acceptedQuantity,
                    'rejected' => $rejectedQuantity,
                ]);
            }

            return $gr->fresh(['lines', 'inspectionLot']);
        });
    }

    /**
     * Post a Goods Receipt: update stock and generate accounting entry.
     *
     * If the GR has products requiring quality inspection AND the inspection has not
     * yet been triggered, this method will create an InspectionLot, put the GR into
     * `in_inspection` status, and throw an exception — the caller must wait for the
     * inspection to be resolved (via resolveInspection()) before calling postGr() again.
     */
    public function postGr(GoodsReceipt $gr): GoodsReceipt
    {
        if (!$gr->canBePosted()) {
            throw new \InvalidArgumentException('Only draft or in-inspection Goods Receipts can be posted.');
        }

        return DB::transaction(function () use ($gr) {
            $gr->load(['lines.product', 'lines.unit', 'purchaseOrder']);

            // Quality inspection gate — only run when coming from draft (not after
            // inspection has already been resolved).
            if ($gr->isDraft()) {
                $lot = $this->triggerInspectionIfRequired($gr);

                if ($lot !== null) {
                    // Re-load the GR to pick up the status change made inside triggerInspectionIfRequired.
                    $gr->refresh();

                    throw new \RuntimeException(
                        "GR {$gr->gr_number} has been placed in quality inspection (lot {$lot->lot_number}). " .
                        'Resolve the inspection before posting.'
                    );
                }
            }

            foreach ($gr->lines as $line) {
                $acceptedQty = $line->getAcceptedQuantity();

                if ($acceptedQty > 0 && $line->product_id && $line->product?->track_inventory) {
                    $this->stockService->recordPurchase(
                        productId: $line->product_id,
                        warehouseId: $gr->warehouse_id,
                        quantity: $acceptedQty,
                        unitCost: (float) $line->unit_cost,
                        variantId: $line->variant_id,
                        referenceNumber: $gr->gr_number,
                        referenceId: $gr->id
                    );

                    // Update moving average price for weighted-average costing products
                    if ($line->product?->costing_method === \App\Models\Inventory\Product::COSTING_WEIGHTED_AVERAGE) {
                        $receivedValue = (float) bcmul((string) $acceptedQty, (string) $line->unit_cost, 4);
                        $this->materialValuationService->updateMovingAveragePrice(
                            productId:     $line->product_id,
                            orgId:         $gr->organization_id,
                            receivedQty:   $acceptedQty,
                            receivedValue: $receivedValue
                        );
                    }
                }
            }

            // Generate GR accounting entry (debit Inventory / Goods Received Not Invoiced).
            // Both the stock movements above and the journal entry below are inside the
            // same DB::transaction — if the journal fails the entire postGr() rolls back,
            // keeping stock and accounting in sync and leaving the GR in its draft state.
            $journalEntryId = $this->createGrJournalEntry($gr);
            $gr->update([
                'status'           => GoodsReceipt::STATUS_POSTED,
                'journal_entry_id' => $journalEntryId,
            ]);

            // Update PO receiving progress
            if ($gr->purchase_order_id) {
                $this->updatePoReceivingProgress($gr->purchaseOrder);
            }

            // Auto-create putaway transfer order for applicable GR lines.
            $this->createPutawayTransferOrder($gr);

            return $gr->fresh(['lines', 'journalEntry']);
        });
    }

    /**
     * Reverse a posted Goods Receipt.
     */
    public function reverseGr(GoodsReceipt $gr, string $reason): GoodsReceipt
    {
        if (!$gr->canBeReversed()) {
            throw new \InvalidArgumentException('Only posted Goods Receipts can be reversed.');
        }

        return DB::transaction(function () use ($gr, $reason) {
            $gr->load(['lines.product']);

            foreach ($gr->lines as $line) {
                $acceptedQty = $line->getAcceptedQuantity();

                if ($acceptedQty > 0 && $line->product_id && $line->product?->track_inventory) {
                    $this->stockService->recordMovement(
                        productId: $line->product_id,
                        warehouseId: $gr->warehouse_id,
                        movementType: StockMovement::TYPE_ADJUSTMENT,
                        direction: StockMovement::DIRECTION_OUT,
                        quantity: $acceptedQty,
                        unitCost: (float) $line->unit_cost,
                        variantId: $line->variant_id,
                        notes: "Reversal of GR {$gr->gr_number}: {$reason}",
                        referenceId: $gr->id
                    );
                }
            }

            $gr->update([
                'status' => GoodsReceipt::STATUS_REVERSED,
                'reversal_reason' => $reason,
                'reversed_at' => now(),
            ]);

            // Revert PO progress if linked
            if ($gr->purchase_order_id) {
                $this->updatePoReceivingProgress($gr->purchaseOrder()->first());
            }

            return $gr->fresh();
        });
    }

    /**
     * Run 3-way match validation for all lines of a bill.
     *
     * Matching strategy: match bill lines to PO lines by product_id (and optionally variant_id),
     * then find GR lines for the same PO lines. This approach works even when bill lines
     * don't carry an explicit po_line_id foreign key.
     */
    public function runThreeWayMatch(Bill $bill): array
    {
        $bill->load(['lines', 'purchaseOrder.lines']);

        if (!$bill->purchase_order_id) {
            return [
                'bill_id' => $bill->id,
                'has_purchase_order' => false,
                'results' => [],
                'summary' => ['matched' => 0, 'unmatched' => 0, 'pending' => 0],
            ];
        }

        return DB::transaction(function () use ($bill) {
            // Remove existing match results for this bill
            ThreeWayMatchResult::where('bill_id', $bill->id)
                ->where('organization_id', $bill->organization_id)
                ->delete();

            $results = [];
            // Key PO lines by product_id for id-based lookups
            $poLines = $bill->purchaseOrder->lines->keyBy('id');
            // Use a composite product_id + variant_id key so that different variants
            // of the same product are matched independently and not conflated.
            $poLinesByProductVariant = $bill->purchaseOrder->lines->keyBy(function ($line) {
                return $line->product_id . '_' . ($line->variant_id ?? '0');
            });

            // Get all GR lines linked to this PO, filtered to the bill's organization
            $grLines = \App\Models\Purchase\GoodsReceiptLine::whereIn(
                'po_line_id',
                $poLines->keys()->toArray()
            )
                ->where('organization_id', $bill->organization_id)
                ->whereHas('goodsReceipt', fn($q) => $q->where('status', GoodsReceipt::STATUS_POSTED))
                ->get()
                ->groupBy('po_line_id');

            foreach ($bill->lines as $billLine) {
                // Match bill line to a PO line via the composite product+variant key
                $matchKey = $billLine->product_id . '_' . ($billLine->variant_id ?? '0');
                $poLine = $poLinesByProductVariant->get($matchKey);

                if (!$poLine) {
                    $result = ThreeWayMatchResult::create([
                        'organization_id' => $bill->organization_id,
                        'bill_id' => $bill->id,
                        'bill_line_id' => $billLine->id,
                        'po_line_id' => null,
                        'gr_line_id' => null,
                        'po_quantity' => null,
                        'gr_quantity' => null,
                        'invoice_quantity' => $billLine->quantity,
                        'po_unit_price' => null,
                        'invoice_unit_price' => $billLine->unit_price,
                        'quantity_match' => false,
                        'price_match' => false,
                        'match_status' => 'missing_gr',
                        'variance_amount' => null,
                    ]);

                    $results[] = $result;
                    continue;
                }

                $grQtyForPoLine = (float) ($grLines->get($poLine->id)?->sum('quantity_received') ?? 0);
                $grLine = $grLines->get($poLine->id)?->first();

                $qtyMatch = abs($grQtyForPoLine - (float) $billLine->quantity) < 0.0001;
                $priceMatch = abs((float) $poLine->unit_price - (float) $billLine->unit_price) < 0.0001;

                $matchStatus = match (true) {
                    $grQtyForPoLine <= 0 => 'missing_gr',
                    !$qtyMatch => 'quantity_variance',
                    !$priceMatch => 'price_variance',
                    default => 'matched',
                };

                $variance = null;

                if ($matchStatus !== 'matched' && $matchStatus !== 'missing_gr') {
                    $variance = abs(
                        ((float) $billLine->unit_price * (float) $billLine->quantity)
                        - ((float) $poLine->unit_price * (float) $poLine->quantity)
                    );
                }

                $result = ThreeWayMatchResult::create([
                    'organization_id' => $bill->organization_id,
                    'bill_id' => $bill->id,
                    'bill_line_id' => $billLine->id,
                    'po_line_id' => $poLine->id,
                    'gr_line_id' => $grLine?->id,
                    'po_quantity' => $poLine->quantity,
                    'gr_quantity' => $grQtyForPoLine > 0 ? $grQtyForPoLine : null,
                    'invoice_quantity' => $billLine->quantity,
                    'po_unit_price' => $poLine->unit_price,
                    'invoice_unit_price' => $billLine->unit_price,
                    'quantity_match' => $qtyMatch,
                    'price_match' => $priceMatch,
                    'match_status' => $matchStatus,
                    'variance_amount' => $variance,
                ]);

                $results[] = $result;
            }

            $summary = [
                'matched' => collect($results)->where('match_status', 'matched')->count(),
                'unmatched' => collect($results)->whereNotIn('match_status', ['matched', 'pending'])->count(),
                'pending' => collect($results)->where('match_status', 'pending')->count(),
                'total' => count($results),
            ];

            return [
                'bill_id' => $bill->id,
                'has_purchase_order' => true,
                'results' => $results,
                'summary' => $summary,
                'all_matched' => $summary['unmatched'] === 0 && $summary['pending'] === 0,
            ];
        });
    }

    /**
     * Update the PO receiving status based on all posted GRs.
     */
    public function updatePoReceivingProgress(PurchaseOrder $purchaseOrder): void
    {
        $poLines = $purchaseOrder->lines;

        foreach ($poLines as $poLine) {
            $receivedQty = \App\Models\Purchase\GoodsReceiptLine::where('po_line_id', $poLine->id)
                ->where('organization_id', $poLine->organization_id)
                ->whereHas('goodsReceipt', fn($q) => $q->where('status', GoodsReceipt::STATUS_POSTED))
                ->sum(\Illuminate\Support\Facades\DB::raw('quantity_received - quantity_rejected'));

            $poLine->update(['quantity_received' => max(0, $receivedQty)]);
        }

        $totalQty = $poLines->sum('quantity');
        $receivedQty = $purchaseOrder->fresh()->lines->sum('quantity_received');

        if ($totalQty <= 0) {
            return;
        }

        $pct = bccomp((string) $totalQty, '0', 4) > 0
            ? bcmul(bcdiv((string) $receivedQty, (string) $totalQty, 8), '100', 4)
            : '0.0000';

        if (bccomp($pct, '100', 4) >= 0) {
            $purchaseOrder->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
        } elseif (bccomp($pct, '0', 4) > 0) {
            $purchaseOrder->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
        }
    }

    /**
     * Create a putaway WarehouseTransferOrder for GR lines that match active
     * PutawayRules in the receiving warehouse.
     *
     * Matching priority (highest first):
     *  1. Exact product match  (rule.product_id === line.product_id)
     *  2. Category match       (rule.product_category_id === product.category_id)
     *  3. Catch-all            (rule has neither product_id nor product_category_id)
     *
     * If at least one matching rule is found across all lines, a single
     * WarehouseTransferOrder is created with one item per matching GR line.
     * GR lines without a matching rule are skipped (manual putaway).
     *
     * Returns the created TO, or null when no rules apply.
     */
    protected function createPutawayTransferOrder(GoodsReceipt $gr): ?WarehouseTransferOrder
    {
        $gr->loadMissing(['lines.product']);

        // Fetch all active putaway rules for the receiving warehouse.
        $rules = PutawayRule::active()
            ->forWarehouse($gr->warehouse_id)
            ->byPriority()
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        /** @var array<int, array{line: GoodsReceiptLine, rule: PutawayRule}> $matchedItems */
        $matchedItems = [];

        foreach ($gr->lines as $line) {
            $acceptedQty = $line->getAcceptedQuantity();

            if ($acceptedQty <= 0 || $line->product_id === null) {
                continue;
            }

            $product    = $line->product;
            $categoryId = $product?->category_id ?? 0;

            // Walk the already-priority-sorted rules and pick the best match.
            $bestRule = null;

            foreach ($rules as $rule) {
                if ($rule->matches((int) $line->product_id, (int) $categoryId)) {
                    $bestRule = $rule;
                    break; // Rules are ordered by priority ASC — first match wins.
                }
            }

            if ($bestRule === null || $bestRule->preferred_location_id === null) {
                continue; // No applicable rule or no target location defined.
            }

            $matchedItems[] = ['line' => $line, 'rule' => $bestRule];
        }

        if (empty($matchedItems)) {
            return null;
        }

        return DB::transaction(function () use ($gr, $matchedItems): WarehouseTransferOrder {
            $toNumber = WarehouseTransferOrder::generateToNumber($gr->organization_id);

            $transferOrder = WarehouseTransferOrder::create([
                'organization_id'     => $gr->organization_id,
                'to_number'           => $toNumber,
                'warehouse_id'        => $gr->warehouse_id,
                'movement_type'       => WarehouseTransferOrder::MOVEMENT_GOODS_RECEIPT,
                'source_document_type' => 'goods_receipt',
                'source_document_ref' => $gr->gr_number,
                'status'              => WarehouseTransferOrder::STATUS_CREATED,
                'created_by'          => auth()->id(),
            ]);

            foreach ($matchedItems as $item) {
                $line = $item['line'];
                $rule = $item['rule'];

                WarehouseTransferOrderItem::create([
                    'transfer_order_id'    => $transferOrder->id,
                    'product_id'           => $line->product_id,
                    'variant_id'           => $line->variant_id,
                    'dest_location_id'     => $rule->preferred_location_id,
                    'requested_quantity'   => $line->getAcceptedQuantity(),
                    'transferred_quantity' => 0,
                    'status'               => WarehouseTransferOrderItem::STATUS_OPEN,
                ]);
            }

            Log::info('Putaway transfer order created from GR', [
                'gr_id' => $gr->id,
                'to_id' => $transferOrder->id,
                'lines' => count($matchedItems),
            ]);

            return $transferOrder;
        });
    }

    private function createGrJournalEntry(GoodsReceipt $gr): ?int
    {
        $totalCost = $gr->getTotalCost();

        if ($totalCost <= 0) {
            return null;
        }

        // Look up Inventory asset account and GRNI liability account by system type
        $inventoryAccount = \App\Models\Accounting\Account::where('organization_id', $gr->organization_id)
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Inventory%')
                    ->orWhere('name', 'like', '%Stock%');
            })
            ->first();

        $grniAccount = \App\Models\Accounting\Account::where('organization_id', $gr->organization_id)
            ->where('account_type', 'liability')
            ->where(function ($q) {
                $q->where('name', 'like', '%GRNI%')
                    ->orWhere('name', 'like', '%Goods Received%')
                    ->orWhere('name', 'like', '%Accrued%');
            })
            ->first();

        if (!$inventoryAccount || !$grniAccount) {
            Log::info('GR journal entry skipped: inventory or GRNI account not configured', [
                'gr_id' => $gr->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createEntry([
            'organization_id' => $gr->organization_id,
            'entry_date' => $gr->gr_date->toDateString(),
            'reference' => $gr->gr_number,
            'description' => "Goods Receipt {$gr->gr_number}",
            'status' => 'posted',
        ], [
            // Debit: Inventory asset account
            [
                'account_id' => $inventoryAccount->id,
                'description' => "Inventory receipt - GR {$gr->gr_number}",
                'debit' => $totalCost,
                'credit' => 0,
            ],
            // Credit: GRNI liability account
            [
                'account_id' => $grniAccount->id,
                'description' => "GRNI accrual - GR {$gr->gr_number}",
                'debit' => 0,
                'credit' => $totalCost,
            ],
        ]);

        return $entry?->id;
    }
}
