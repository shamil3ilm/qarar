<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\ConsignmentMovement;
use App\Models\Sales\ConsignmentOrder;
use App\Models\Sales\ConsignmentOrderLine;
use App\Models\Sales\ConsignmentStock;
use App\Models\Sales\Contact;
use App\Models\Inventory\Product;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsignmentService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private InvoiceService $invoiceService
    ) {}

    /**
     * Create a Consignment Fill-up order.
     * Goods are shipped to customer premises; no revenue posted yet.
     *
     * @param  array{contact_id:int,order_date:string,branch_id?:int,notes?:string,lines:array,organization_id:int,created_by:int}  $data
     */
    public function createFillup(array $data): ConsignmentOrder
    {
        return $this->createOrder(ConsignmentOrder::TYPE_FILLUP, $data);
    }

    /**
     * Create a Consignment Issue order.
     * Customer reports consumption; revenue is recognised and invoice is generated
     * when the order is completed.
     *
     * @param  array{contact_id:int,order_date:string,branch_id?:int,notes?:string,lines:array,organization_id:int,created_by:int}  $data
     */
    public function createIssue(array $data): ConsignmentOrder
    {
        return $this->createOrder(ConsignmentOrder::TYPE_ISSUE, $data);
    }

    /**
     * Create a Consignment Pickup order.
     * Unconsumed goods are returned from the customer to the company warehouse.
     *
     * @param  array{contact_id:int,order_date:string,branch_id?:int,notes?:string,lines:array,organization_id:int,created_by:int}  $data
     */
    public function createPickup(array $data): ConsignmentOrder
    {
        return $this->createOrder(ConsignmentOrder::TYPE_PICKUP, $data);
    }

    /**
     * Create a Consignment Return order.
     * Customer returns previously issued (billed) goods.
     * A credit-note flow is triggered on completion.
     *
     * @param  array{contact_id:int,order_date:string,branch_id?:int,notes?:string,lines:array,organization_id:int,created_by:int}  $data
     */
    public function createReturn(array $data): ConsignmentOrder
    {
        return $this->createOrder(ConsignmentOrder::TYPE_RETURN, $data);
    }

    /**
     * Confirm a draft consignment order.
     */
    public function confirm(ConsignmentOrder $order): ConsignmentOrder
    {
        if (!$order->canBeConfirmed()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be confirmed from status '{$order->status}'."
            );
        }

        $order->update(['status' => ConsignmentOrder::STATUS_CONFIRMED]);

        return $order->fresh();
    }

    /**
     * Mark a confirmed order as shipped.
     */
    public function ship(ConsignmentOrder $order): ConsignmentOrder
    {
        if (!$order->canBeShipped()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be shipped from status '{$order->status}'."
            );
        }

        $order->update(['status' => ConsignmentOrder::STATUS_SHIPPED]);

        // For fill-ups, record the stock movement when the goods leave the warehouse.
        if ($order->order_type === ConsignmentOrder::TYPE_FILLUP) {
            $this->postStockMovements($order, ConsignmentMovement::TYPE_IN);
        }

        return $order->fresh();
    }

    /**
     * Complete a consignment order.
     *
     * - Fill-up: nothing extra (stock was already moved on ship).
     * - Issue:   deduct from consignment stock, generate invoice.
     * - Pickup:  move stock back (out from consignment).
     * - Return:  move stock back, flag for credit-note creation.
     */
    public function complete(ConsignmentOrder $order): ConsignmentOrder
    {
        if (!$order->canBeCompleted()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be completed from status '{$order->status}'."
            );
        }

        return DB::transaction(function () use ($order) {
            switch ($order->order_type) {
                case ConsignmentOrder::TYPE_FILLUP:
                    // Stock already posted on ship; just mark complete.
                    if ($order->status === ConsignmentOrder::STATUS_CONFIRMED) {
                        // Shipped step was skipped — post movements now.
                        $this->postStockMovements($order, ConsignmentMovement::TYPE_IN);
                    }
                    break;

                case ConsignmentOrder::TYPE_ISSUE:
                    $this->postStockMovements($order, ConsignmentMovement::TYPE_OUT);
                    $invoice = $this->generateInvoiceForIssue($order);
                    $order->update(['invoice_id' => $invoice->id]);
                    break;

                case ConsignmentOrder::TYPE_PICKUP:
                    $this->postStockMovements($order, ConsignmentMovement::TYPE_OUT);
                    break;

                case ConsignmentOrder::TYPE_RETURN:
                    // Returned goods go back into consignment stock.
                    $this->postStockMovements($order, ConsignmentMovement::TYPE_IN);
                    break;
            }

            $order->update(['status' => ConsignmentOrder::STATUS_COMPLETED]);

            return $order->fresh(['lines', 'contact']);
        });
    }

    /**
     * Cancel a consignment order (only allowed while draft or confirmed).
     */
    public function cancel(ConsignmentOrder $order): ConsignmentOrder
    {
        if (!$order->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Order {$order->order_number} cannot be cancelled from status '{$order->status}'."
            );
        }

        $order->update(['status' => ConsignmentOrder::STATUS_CANCELLED]);

        return $order->fresh();
    }

    /**
     * Get current consignment stock level for a contact / product combination.
     */
    public function getConsignmentStock(Contact $contact, Product $product): ?ConsignmentStock
    {
        return ConsignmentStock::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->where('product_id', $product->id)
            ->first();
    }

    /**
     * Return a summary of all consignment stock and recent movements for a contact.
     *
     * @return array{contact_id:int,stocks:Collection,totals:array}
     */
    public function getConsignmentStatement(Contact $contact): array
    {
        $stocks = ConsignmentStock::with(['product', 'variant', 'warehouse'])
            ->where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->get();

        $totals = [
            'total_items'    => $stocks->count(),
            'total_quantity' => $stocks->sum(fn ($s) => (float) $s->on_hand_quantity),
        ];

        $recentMovements = ConsignmentMovement::whereIn(
            'consignment_stock_id',
            $stocks->pluck('id')
        )
            ->with(['order'])
            ->orderByDesc('moved_at')
            ->limit(50)
            ->get();

        return [
            'contact_id'       => $contact->id,
            'stocks'           => $stocks,
            'recent_movements' => $recentMovements,
            'totals'           => $totals,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new consignment order header + lines inside a transaction.
     */
    private function createOrder(string $type, array $data): ConsignmentOrder
    {
        return DB::transaction(function () use ($type, $data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $prefix = match ($type) {
                ConsignmentOrder::TYPE_FILLUP  => 'CFU',
                ConsignmentOrder::TYPE_ISSUE   => 'CIS',
                ConsignmentOrder::TYPE_PICKUP  => 'CPU',
                ConsignmentOrder::TYPE_RETURN  => 'CRT',
                default                        => 'CON',
            };

            $order = ConsignmentOrder::create(array_merge($data, [
                'order_type'   => $type,
                'order_number' => $this->numberGenerator->generate($prefix),
                'status'       => ConsignmentOrder::STATUS_DRAFT,
            ]));

            foreach ($lines as $line) {
                $lineModel = ConsignmentOrderLine::create(array_merge($line, [
                    'order_id' => $order->id,
                    'tax_rate' => $line['tax_rate'] ?? 0,
                ]));
                $lineModel->calculateTotal();
                $lineModel->save();
            }

            return $order->fresh(['lines', 'contact']);
        });
    }

    /**
     * Post stock movements for all lines of an order.
     * Creates / updates ConsignmentStock records and logs ConsignmentMovement entries.
     */
    private function postStockMovements(ConsignmentOrder $order, string $movementType): void
    {
        $order->load('lines');

        foreach ($order->lines as $line) {
            $stock = ConsignmentStock::firstOrCreate(
                [
                    'organization_id' => $order->organization_id,
                    'contact_id'      => $order->contact_id,
                    'product_id'      => $line->product_id,
                    'variant_id'      => $line->variant_id,
                    'warehouse_id'    => $line->warehouse_id,
                ],
                ['on_hand_quantity' => 0]
            );

            $qty = (float) $line->quantity;

            if ($movementType === ConsignmentMovement::TYPE_IN) {
                $newBalance = bcadd((string) $stock->on_hand_quantity, (string) $qty, 4);
            } else {
                $newBalance = bcsub((string) $stock->on_hand_quantity, (string) $qty, 4);
                if (bccomp($newBalance, '0', 4) < 0) {
                    throw new \InvalidArgumentException(
                        "Insufficient consignment stock. Available: {$stock->on_hand_quantity}, Requested: {$qty}"
                    );
                }
            }

            $stock->update([
                'on_hand_quantity' => $newBalance,
                'last_updated_at'  => now(),
            ]);

            ConsignmentMovement::create([
                'consignment_stock_id' => $stock->id,
                'order_id'             => $order->id,
                'movement_type'        => $movementType,
                'quantity'             => $qty,
                'balance_after'        => $newBalance,
                'moved_at'             => now(),
            ]);
        }
    }

    /**
     * Generate an invoice for a consignment issue order.
     * Delegates to InvoiceService following existing invoice creation patterns.
     */
    private function generateInvoiceForIssue(ConsignmentOrder $order): \App\Models\Sales\Invoice
    {
        $order->load(['lines', 'contact']);

        $invoiceLines = $order->lines->map(function (ConsignmentOrderLine $line): array {
            return [
                'product_id'  => $line->product_id,
                'description' => null,
                'quantity'    => (float) $line->quantity,
                'unit_price'  => (float) ($line->unit_price ?? 0),
                'discount_type'   => null,
                'discount_value'  => 0,
                'tax_category_id' => null,
            ];
        })->all();

        $invoiceData = [
            'customer_id'    => $order->contact_id,
            'branch_id'      => $order->branch_id,
            'invoice_date'   => now()->toDateString(),
            'invoice_type'   => 'standard',
            'notes'          => "Consignment Issue: {$order->order_number}",
            'organization_id' => $order->organization_id,
        ];

        return $this->invoiceService->create($invoiceData, $invoiceLines);
    }
}
