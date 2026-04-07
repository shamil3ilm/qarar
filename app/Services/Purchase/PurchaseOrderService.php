<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\Organization;
use App\Models\Core\UserEvent;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ReleaseStrategy;
use App\Models\Sales\Contact;
use App\Services\Core\ApprovalWorkflowService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Core\UserEventService;
use App\Services\Inventory\StockService;
use App\Services\Purchase\ReleaseStrategyService;
use App\Services\Tax\TaxCalculatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function __construct(
        private TaxCalculatorService $taxCalculator,
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator,
        private UserEventService $userEventService,
        private ApprovalWorkflowService $approvalWorkflowService,
        private ReleaseStrategyService $releaseStrategyService,
    ) {}

    /**
     * Create a new purchase order.
     */
    public function create(array $data, array $lines): PurchaseOrder
    {
        $order = DB::transaction(function () use ($data, $lines) {
            $organization = Organization::findOrFail(auth()->user()->organization_id);
            $supplier = Contact::where('organization_id', auth()->user()->organization_id)
                ->findOrFail($data['supplier_id']);

            if (empty($data['order_number'])) {
                $data['order_number'] = $this->numberGenerator->generate('PO');
            }

            $data['supplier_name'] = $supplier->getDisplayName();
            $data['supplier_email'] = $supplier->email;
            $data['supplier_address'] = $data['supplier_address'] ?? $supplier->getBillingAddress();

            $data['currency_code'] = $data['currency_code'] ?? $supplier->currency_code ?? $organization->base_currency;
            $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
            $data['status'] = $data['status'] ?? PurchaseOrder::STATUS_DRAFT;

            $order = PurchaseOrder::create($data);

            $taxResult = $this->taxCalculator->calculate(
                $organization,
                $lines,
                $data['place_of_supply'] ?? null
            );

            foreach ($lines as $index => $lineData) {
                $taxLine = $taxResult->lines[$index] ?? [];

                if (empty($lineData['description'])) {
                    $product = isset($lineData['product_id'])
                        ? \App\Models\Inventory\Product::withoutGlobalScopes()
                            ->where('organization_id', $order->organization_id)
                            ->find($lineData['product_id'])
                        : null;
                    $lineData['description'] = $product?->name ?? '';
                }

                $order->lines()->create(array_merge($lineData, [
                    'tax_rate' => $taxLine['tax_rate'] ?? 0,
                    'tax_amount' => $taxLine['tax_amount'] ?? 0,
                    'tax_code' => $taxLine['tax_code'] ?? 'S',
                    'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                    'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                    'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                    'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                    'igst_rate' => $taxLine['igst_rate'] ?? 0,
                    'igst_amount' => $taxLine['igst_amount'] ?? 0,
                    'line_order' => $index,
                ]));
            }

            $order->recalculateTotals();
            $order = $order->fresh(['lines', 'supplier']);

            // --- PO Release: auto-route for approval when required ---
            $userId = auth('api')->id() ?? 0;
            if ($this->requiresApproval($order)) {
                $this->submitForApproval($order, $userId);

                // Initiate multi-level release strategy when one is configured
                $this->releaseStrategyService->initiate(
                    ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER,
                    $order->id,
                    (float) $order->total
                );
            }

            return $order->fresh(['lines', 'supplier']);
        });

        try {
            $this->userEventService->track(
                UserEvent::PURCHASE_ORDER_CREATED,
                ['order_id' => $order->id, 'order_number' => $order->order_number, 'total' => $order->total],
                auth('api')->id(),
                $order->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::PURCHASE_ORDER_CREATED, 'error' => $e->getMessage()]);
        }

        return $order;
    }

    /**
     * Update a draft purchase order.
     */
    public function update(PurchaseOrder $order, array $data, ?array $lines = null): PurchaseOrder
    {
        if (!$order->isEditable()) {
            throw new \InvalidArgumentException('Only draft/sent orders can be updated.');
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            if (isset($data['version']) && $data['version'] !== $order->version) {
                throw new \App\Exceptions\ConcurrencyException(
                    'Purchase order has been modified by another user.',
                    $order
                );
            }

            $order->update(array_merge($data, ['version' => $order->version + 1]));

            if ($lines !== null) {
                $organization = Organization::findOrFail($order->organization_id);
                $order->lines()->delete();

                $taxResult = $this->taxCalculator->calculate(
                    $organization,
                    $lines,
                    $order->place_of_supply ?? null
                );

                foreach ($lines as $index => $lineData) {
                    $taxLine = $taxResult->lines[$index] ?? [];

                    $order->lines()->create(array_merge($lineData, [
                        'tax_rate' => $taxLine['tax_rate'] ?? 0,
                        'tax_amount' => $taxLine['tax_amount'] ?? 0,
                        'tax_code' => $taxLine['tax_code'] ?? 'S',
                        'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                        'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                        'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                        'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                        'igst_rate' => $taxLine['igst_rate'] ?? 0,
                        'igst_amount' => $taxLine['igst_amount'] ?? 0,
                        'line_order' => $index,
                    ]));
                }

                $order->recalculateTotals();
            }

            return $order->fresh(['lines', 'supplier']);
        });
    }

    /**
     * Send purchase order to supplier.
     */
    public function send(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft orders can be sent.');
        }

        $order->transitionTo(PurchaseOrder::STATUS_SENT);

        return $order->fresh();
    }

    /**
     * Confirm a purchase order.
     */
    public function confirm(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if (!in_array($order->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT], true)) {
            throw new \InvalidArgumentException('Only draft/sent orders can be confirmed.');
        }

        $order->update([
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $order = $order->fresh();

        try {
            $this->userEventService->track(
                UserEvent::PURCHASE_ORDER_APPROVED,
                ['order_id' => $order->id, 'order_number' => $order->order_number],
                auth('api')->id(),
                $order->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::PURCHASE_ORDER_APPROVED, 'error' => $e->getMessage()]);
        }

        return $order;
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(PurchaseOrder $order, string $reason = ''): PurchaseOrder
    {
        if (in_array($order->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException('Cannot cancel orders that are received, billed, or already cancelled.');
        }

        $order->update(['notes' => $order->notes . "\n\nCancelled: " . $reason]);
        $order->transitionTo(PurchaseOrder::STATUS_CANCELLED);

        return $order->fresh();
    }

    /**
     * Receive items against a purchase order.
     */
    public function receive(PurchaseOrder $order, array $lineQuantities, ?int $warehouseId = null): PurchaseOrder
    {
        if (!$order->canBeReceived()) {
            throw new \InvalidArgumentException('Purchase order cannot be received in current status.');
        }

        return DB::transaction(function () use ($order, $lineQuantities, $warehouseId) {
            foreach ($lineQuantities as $lineId => $quantity) {
                $line = $order->lines()->findOrFail($lineId);

                $remainingToReceive = $line->getRemainingToReceive();
                $quantityToReceive = min($quantity, $remainingToReceive);

                if ($quantityToReceive <= 0) {
                    continue;
                }

                if ($line->product_id && $line->product?->track_inventory) {
                    $targetWarehouse = $warehouseId ?? $line->warehouse_id ?? $order->warehouse_id;

                    if ($targetWarehouse) {
                        $this->stockService->recordPurchase(
                            productId: $line->product_id,
                            warehouseId: $targetWarehouse,
                            quantity: $quantityToReceive,
                            unitCost: $line->unit_price,
                            variantId: $line->variant_id,
                            referenceNumber: $order->order_number,
                            referenceId: $order->id
                        );
                    }
                }

                $line->increment('quantity_received', $quantityToReceive);
            }

            $this->updateReceivingStatus($order);

            return $order->fresh(['lines', 'supplier']);
        });
    }

    /**
     * Update order status based on receiving progress.
     */
    protected function updateReceivingStatus(PurchaseOrder $order): void
    {
        $progress = $order->fresh()->getReceivingProgress();

        if ($progress['receiving_percentage'] >= 100) {
            $order->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'delivery_date' => now(),
            ]);
        } elseif ($progress['receiving_percentage'] > 0) {
            $order->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
        }
    }

    /**
     * Get purchase orders summary.
     */
    public function getSummary(?int $supplierId = null): array
    {
        $query = PurchaseOrder::query();

        if ($supplierId) {
            $query->forSupplier($supplierId);
        }

        $draft = (clone $query)->draft()->count();
        $pendingReceipt = (clone $query)->pendingReceipt()->count();
        $open = (clone $query)->open()->count();

        $pendingValue = (clone $query)->pendingReceipt()->sum('total');

        return [
            'total_count' => $query->count(),
            'draft_count' => $draft,
            'pending_receipt_count' => $pendingReceipt,
            'open_count' => $open,
            'pending_receipt_value' => (float) $pendingValue,
        ];
    }

    /**
     * Duplicate a purchase order.
     */
    public function duplicate(PurchaseOrder $order): PurchaseOrder
    {
        $lines = $order->lines->map(function ($line) {
            return [
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_id' => $line->unit_id,
                'unit_price' => $line->unit_price,
                'discount_type' => $line->discount_type,
                'discount_value' => $line->discount_value,
                'tax_category_id' => $line->tax_category_id,
                'warehouse_id' => $line->warehouse_id,
            ];
        })->toArray();

        return $this->create([
            'supplier_id' => $order->supplier_id,
            'warehouse_id' => $order->warehouse_id,
            'delivery_address' => $order->delivery_address,
            'order_date' => now(),
            'branch_id' => $order->branch_id,
            'currency_code' => $order->currency_code,
            'exchange_rate' => $order->exchange_rate,
            'discount_type' => $order->discount_type,
            'discount_value' => $order->discount_value,
            'notes' => $order->notes,
            'terms_and_conditions' => $order->terms_and_conditions,
        ], $lines);
    }

    // -------------------------------------------------------------------------
    // PO Release / Approval
    // -------------------------------------------------------------------------

    /**
     * Determine whether this purchase order must go through an approval
     * workflow before it can be confirmed.
     *
     * A PO requires approval when:
     *   - Its total exceeds the configured threshold, OR
     *   - The organisation has an active ApprovalWorkflow for PurchaseOrder.
     */
    protected function requiresApproval(PurchaseOrder $po): bool
    {
        $threshold = (float) config('erp.po_approval_threshold', 10000);

        if ((float) $po->total > $threshold) {
            return true;
        }

        return ApprovalWorkflow::active()
            ->forType(PurchaseOrder::class)
            ->exists();
    }

    /**
     * Submit the PO into the approval workflow and set its status to
     * pending_approval.
     */
    protected function submitForApproval(PurchaseOrder $po, int $userId): void
    {
        try {
            $this->approvalWorkflowService->submit(
                approvable: $po,
                userId: $userId,
                amount: (float) $po->total,
                notes: "Auto-submitted: PO {$po->order_number} requires approval."
            );
        } catch (\InvalidArgumentException $e) {
            // No workflow configured – fall back to setting status manually so
            // it is still flagged for manual approval.
            Log::info('PO approval: no workflow found, setting status to pending_approval manually.', [
                'po_id'     => $po->id,
                'po_number' => $po->order_number,
                'reason'    => $e->getMessage(),
            ]);

            $po->update(['status' => PurchaseOrder::STATUS_PENDING_APPROVAL]);
        }
    }

    /**
     * Approve a PO that is pending approval.
     *
     * When a release strategy applies:
     *   1. Advances the current pending level in the release strategy.
     *   2. If fully released, marks the PO as confirmed.
     *   3. If not fully released, returns the PO still in pending_approval state.
     *
     * When no release strategy applies, falls back to single-step approval.
     */
    public function approvePO(PurchaseOrder $po, int $userId, ?string $notes = null): PurchaseOrder
    {
        if (!$po->isPendingApproval()) {
            throw new \InvalidArgumentException('Only purchase orders pending approval can be approved.');
        }

        // Check for an active release strategy
        $currentLevel = $this->releaseStrategyService->getCurrentLevel(
            ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER,
            $po->id
        );

        if ($currentLevel !== null) {
            $approver = \App\Models\User::findOrFail($userId);
            $fullyReleased = $this->releaseStrategyService->approve($currentLevel, $approver, $notes);

            if (! $fullyReleased) {
                // More levels remain — keep PO in pending_approval
                return $po->fresh(['lines', 'supplier']);
            }

            // All levels approved — fall through to mark PO confirmed below
        }

        $po->update([
            'status'      => PurchaseOrder::STATUS_CONFIRMED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'notes'       => $po->notes . ($notes ? "\n\nApproval notes: {$notes}" : ''),
        ]);

        try {
            $this->userEventService->track(
                UserEvent::PURCHASE_ORDER_APPROVED,
                ['order_id' => $po->id, 'order_number' => $po->order_number],
                $userId,
                $po->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::PURCHASE_ORDER_APPROVED, 'error' => $e->getMessage()]);
        }

        return $po->fresh(['lines', 'supplier']);
    }

    /**
     * Reject a PO that is pending approval, reverting it to draft.
     */
    public function rejectPO(PurchaseOrder $po, int $userId, string $reason): PurchaseOrder
    {
        if (!$po->isPendingApproval()) {
            throw new \InvalidArgumentException('Only purchase orders pending approval can be rejected.');
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_DRAFT,
            'notes'  => $po->notes . "\n\nRejected by user #{$userId}: {$reason}",
        ]);

        return $po->fresh(['lines', 'supplier']);
    }
}
