<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\SubcontractComponent;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Manufacturing\SubcontractOrderLine;
use App\Models\Manufacturing\SubcontractReceipt;
use App\Models\Manufacturing\SubcontractReceiptLine;
use App\Models\Manufacturing\SubcontractTransfer;
use App\Models\Manufacturing\SubcontractTransferLine;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

class SubcontractingService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
    ) {}

    /**
     * Create a new subcontract order with its lines and components.
     */
    public function createOrder(array $data): SubcontractOrder
    {
        return DB::transaction(function () use ($data) {
            $order = SubcontractOrder::create([
                'organization_id'       => auth()->user()->organization_id,
                'order_number'          => $this->numberGenerator->generate('SCO'),
                'contact_id'            => $data['contact_id'],
                'status'                => SubcontractOrder::STATUS_DRAFT,
                'issued_date'           => $data['issued_date'] ?? null,
                'expected_receipt_date' => $data['expected_receipt_date'] ?? null,
                'currency_code'         => $data['currency_code'] ?? 'USD',
                'service_charge'        => $data['service_charge'] ?? 0,
                'notes'                 => $data['notes'] ?? null,
                'purchase_order_id'     => $data['purchase_order_id'] ?? null,
                'branch_id'             => $data['branch_id'] ?? null,
                'created_by'            => auth()->id(),
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                SubcontractOrderLine::create([
                    'order_id'             => $order->id,
                    'product_id'           => $line['product_id'],
                    'variant_id'           => $line['variant_id'] ?? null,
                    'ordered_quantity'     => $line['ordered_quantity'],
                    'received_quantity'    => 0,
                    'unit_id'              => $line['unit_id'],
                    'unit_service_charge'  => $line['unit_service_charge'] ?? 0,
                    'total_service_charge' => bcmul(
                        (string) ($line['unit_service_charge'] ?? 0),
                        (string) $line['ordered_quantity'],
                        4
                    ),
                    'scrap_quantity'       => 0,
                ]);
            }

            foreach ($data['components'] ?? [] as $component) {
                SubcontractComponent::create([
                    'order_id'             => $order->id,
                    'product_id'           => $component['product_id'],
                    'variant_id'           => $component['variant_id'] ?? null,
                    'required_quantity'    => $component['required_quantity'],
                    'transferred_quantity' => 0,
                    'unit_id'              => $component['unit_id'],
                    'warehouse_id'         => $component['warehouse_id'],
                ]);
            }

            return $order->fresh(['lines', 'components']);
        });
    }

    /**
     * Mark order as sent to vendor (status transition: draft → sent).
     */
    public function sendToVendor(SubcontractOrder $order): SubcontractOrder
    {
        if (!$order->isDraft()) {
            throw new \InvalidArgumentException('Only draft orders can be sent to a vendor.');
        }

        $order->update(['status' => SubcontractOrder::STATUS_SENT]);

        return $order->fresh();
    }

    /**
     * Transfer raw materials to the vendor and update stock levels.
     *
     * $items = [
     *   ['component_id' => int, 'quantity' => float, 'batch_number' => ?string],
     *   ...
     * ]
     */
    public function transferMaterialsToVendor(SubcontractOrder $order, array $items): SubcontractTransfer
    {
        if (!in_array($order->status, [
            SubcontractOrder::STATUS_SENT,
            SubcontractOrder::STATUS_MATERIAL_TRANSFERRED,
            SubcontractOrder::STATUS_IN_PROCESS,
        ], true)) {
            throw new \InvalidArgumentException(
                'Materials can only be transferred when the order is in sent, material_transferred, or in_process status.'
            );
        }

        return DB::transaction(function () use ($order, $items) {
            // Preload components to avoid N+1
            $componentIds = array_column($items, 'component_id');
            $components   = SubcontractComponent::whereIn('id', $componentIds)->get()->keyBy('id');

            // Validate requested quantities
            foreach ($items as $item) {
                $component = $components->get($item['component_id'])
                    ?? throw new \InvalidArgumentException("Component {$item['component_id']} not found.");

                if ($component->order_id !== $order->id) {
                    throw new \InvalidArgumentException('Component does not belong to this order.');
                }

                $qty = (float) $item['quantity'];
                if ($qty <= 0) {
                    throw new \InvalidArgumentException('Transfer quantity must be positive.');
                }

                if ($qty > $component->getRemainingQuantity()) {
                    throw new \InvalidArgumentException(
                        "Transfer quantity exceeds remaining required quantity for component {$component->id}."
                    );
                }
            }

            // Use the warehouse from the first component as the source warehouse
            $firstComponent = $components->first();
            $warehouseId    = $firstComponent->warehouse_id;

            $transfer = SubcontractTransfer::create([
                'order_id'      => $order->id,
                'transfer_date' => now()->toDateString(),
                'transfer_type' => SubcontractTransfer::TYPE_OUTWARD,
                'warehouse_id'  => $warehouseId,
                'created_by'    => auth()->id(),
            ]);

            foreach ($items as $item) {
                $component = $components->get($item['component_id']);
                $qty       = (float) $item['quantity'];

                SubcontractTransferLine::create([
                    'transfer_id'       => $transfer->id,
                    'product_id'        => $component->product_id,
                    'variant_id'        => $component->variant_id,
                    'component_line_id' => $component->id,
                    'quantity'          => $qty,
                    'unit_id'           => $component->unit_id,
                    'batch_number'      => $item['batch_number'] ?? null,
                ]);

                // Deduct from stock
                $this->stockService->recordMovement(
                    productId: $component->product_id,
                    warehouseId: $component->warehouse_id,
                    movementType: 'subcontract_transfer_out',
                    direction: 'out',
                    quantity: $qty,
                    unitCost: 0.0,
                    referenceType: SubcontractOrder::class,
                    referenceId: $order->id,
                    notes: "Subcontract material transfer for order {$order->order_number}",
                );

                // Update transferred quantity on component
                $component->update([
                    'transferred_quantity' => bcadd(
                        (string) $component->transferred_quantity,
                        (string) $qty,
                        4
                    ),
                ]);
            }

            // Advance order status
            $allTransferred = $order->components()->get()->every(
                fn (SubcontractComponent $c) => $c->fresh()->isFullyTransferred()
            );

            $newStatus = $allTransferred
                ? SubcontractOrder::STATUS_IN_PROCESS
                : SubcontractOrder::STATUS_MATERIAL_TRANSFERRED;

            $order->update(['status' => $newStatus]);

            return $transfer->fresh(['lines']);
        });
    }

    /**
     * Record goods received from the vendor, update stock, and optionally post GL.
     *
     * $receiptData = [
     *   'warehouse_id' => int,
     *   'receipt_date' => date string,
     *   'notes'        => ?string,
     *   'lines'        => [
     *     ['order_line_id' => int, 'quantity_received' => float, 'quantity_rejected' => float,
     *      'unit_cost' => float, 'batch_number' => ?string, 'expiry_date' => ?string],
     *     ...
     *   ],
     * ]
     */
    public function receiveFromVendor(SubcontractOrder $order, array $receiptData): SubcontractReceipt
    {
        if (!$order->canReceive()) {
            throw new \InvalidArgumentException(
                'Order must be in material_transferred or in_process status to receive goods.'
            );
        }

        return DB::transaction(function () use ($order, $receiptData) {
            $receipt = SubcontractReceipt::create([
                'order_id'     => $order->id,
                'receipt_date' => $receiptData['receipt_date'] ?? now()->toDateString(),
                'warehouse_id' => $receiptData['warehouse_id'],
                'status'       => SubcontractReceipt::STATUS_DRAFT,
                'notes'        => $receiptData['notes'] ?? null,
                'created_by'   => auth()->id(),
            ]);

            // Preload order lines
            $orderLineIds = array_column($receiptData['lines'], 'order_line_id');
            $orderLines   = SubcontractOrderLine::whereIn('id', $orderLineIds)->get()->keyBy('id');

            foreach ($receiptData['lines'] as $lineData) {
                $orderLine = $orderLines->get($lineData['order_line_id'])
                    ?? throw new \InvalidArgumentException("Order line {$lineData['order_line_id']} not found.");

                if ($orderLine->order_id !== $order->id) {
                    throw new \InvalidArgumentException('Order line does not belong to this subcontract order.');
                }

                $qtyReceived = (float) ($lineData['quantity_received'] ?? 0);
                $qtyRejected = (float) ($lineData['quantity_rejected'] ?? 0);
                $unitCost    = (float) ($lineData['unit_cost'] ?? 0);
                $totalCost   = (float) bcmul((string) $qtyReceived, (string) $unitCost, 4);

                SubcontractReceiptLine::create([
                    'receipt_id'        => $receipt->id,
                    'order_line_id'     => $orderLine->id,
                    'product_id'        => $orderLine->product_id,
                    'quantity_received' => $qtyReceived,
                    'quantity_rejected' => $qtyRejected,
                    'unit_id'           => $orderLine->unit_id,
                    'unit_cost'         => $unitCost,
                    'total_cost'        => $totalCost,
                    'batch_number'      => $lineData['batch_number'] ?? null,
                    'expiry_date'       => $lineData['expiry_date'] ?? null,
                ]);

                $acceptedQty = $qtyReceived - $qtyRejected;

                if ($acceptedQty > 0) {
                    // Add accepted quantity to warehouse stock
                    $this->stockService->recordMovement(
                        productId: $orderLine->product_id,
                        warehouseId: $receiptData['warehouse_id'],
                        movementType: 'subcontract_receipt',
                        direction: 'in',
                        quantity: $acceptedQty,
                        unitCost: $unitCost,
                        referenceType: SubcontractOrder::class,
                        referenceId: $order->id,
                        notes: "Subcontract receipt for order {$order->order_number}",
                    );
                }

                // Update received quantity on the order line
                $orderLine->update([
                    'received_quantity' => bcadd(
                        (string) $orderLine->received_quantity,
                        (string) $qtyReceived,
                        4
                    ),
                    'scrap_quantity' => bcadd(
                        (string) $orderLine->scrap_quantity,
                        (string) $qtyRejected,
                        4
                    ),
                ]);
            }

            // Post the receipt
            $receipt->update(['status' => SubcontractReceipt::STATUS_POSTED]);

            // Advance order status
            $allReceived = $order->lines()->get()->every(
                fn (SubcontractOrderLine $l) => $l->fresh()->isFullyReceived()
            );

            $order->update([
                'status' => $allReceived
                    ? SubcontractOrder::STATUS_RECEIVED
                    : SubcontractOrder::STATUS_IN_PROCESS,
            ]);

            return $receipt->fresh(['lines.product']);
        });
    }

    /**
     * Close the subcontract order — settle outstanding quantities and mark closed.
     */
    public function closeOrder(SubcontractOrder $order): SubcontractOrder
    {
        if (!$order->canClose()) {
            throw new \InvalidArgumentException(
                'Order must be in received or in_process status to be closed.'
            );
        }

        return DB::transaction(function () use ($order) {
            // Mark any outstanding line quantities as scrap/loss
            foreach ($order->lines as $line) {
                $remaining = $line->getRemainingQuantity();
                if ($remaining > 0) {
                    $line->update([
                        'scrap_quantity' => bcadd((string) $line->scrap_quantity, (string) $remaining, 4),
                        'received_quantity' => $line->ordered_quantity,
                    ]);
                }
            }

            $order->update(['status' => SubcontractOrder::STATUS_CLOSED]);

            return $order->fresh(['lines', 'components']);
        });
    }

    /**
     * Cancel a draft or sent subcontract order.
     */
    public function cancel(SubcontractOrder $order): SubcontractOrder
    {
        if (!$order->canBeCancelled()) {
            throw new \InvalidArgumentException('Only draft or sent orders can be cancelled.');
        }

        $order->update(['status' => SubcontractOrder::STATUS_CANCELLED]);

        return $order->fresh();
    }
}
