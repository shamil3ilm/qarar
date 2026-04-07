<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\ThirdPartyOrder;
use App\Models\Sales\ThirdPartyOrderLine;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ThirdPartyOrderService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ThirdPartyOrder::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        return $query->with(['salesOrder', 'vendor', 'purchaseOrder', 'lines.product'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): ThirdPartyOrder
    {
        return DB::transaction(function () use ($data): ThirdPartyOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $order = ThirdPartyOrder::create($data);

            foreach ($lines as $line) {
                $order->lines()->create(array_merge($line, [
                    'organization_id' => $order->organization_id,
                ]));
            }

            return $order->load(['salesOrder', 'vendor', 'lines.product']);
        });
    }

    public function update(ThirdPartyOrder $order, array $data): ThirdPartyOrder
    {
        $order->update($data);
        return $order->fresh(['salesOrder', 'vendor', 'purchaseOrder', 'lines.product']);
    }

    public function createPurchaseOrder(ThirdPartyOrder $order): PurchaseOrder
    {
        if (!$order->canCreatePO()) {
            throw new \RuntimeException('Cannot create PO: order is not in pending status or PO already exists.');
        }

        return DB::transaction(function () use ($order): PurchaseOrder {
            $po = PurchaseOrder::create([
                'organization_id' => $order->organization_id,
                'supplier_id' => $order->vendor_id,
                'order_number' => $this->numberGenerator->generate('PO', null, $order->organization_id),
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $order->estimated_delivery_date,
                'status' => 'draft',
                'notes' => 'Drop shipment for Third-Party Order #' . $order->id,
                'currency_code' => 'SAR',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
            ]);

            foreach ($order->lines as $line) {
                $lineTotal = (float) $line->quantity * (float) ($line->vendor_price ?? $line->unit_price);
                $po->lines()->create([
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->vendor_price ?? $line->unit_price,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'subtotal' => $lineTotal,
                    'total' => $lineTotal,
                ]);
            }

            // Recalculate PO total
            $total = $po->lines()->sum(DB::raw('quantity * unit_price'));
            $po->update(['subtotal' => $total, 'total' => $total]);

            // Link PO back to TPO
            $order->update([
                'purchase_order_id' => $po->id,
                'status' => ThirdPartyOrder::STATUS_PO_CREATED,
            ]);

            return $po;
        });
    }

    public function confirmShipment(ThirdPartyOrder $order, array $data): ThirdPartyOrder
    {
        $order->update([
            'status' => ThirdPartyOrder::STATUS_SHIPPED,
            'shipping_confirmation' => $data['shipping_confirmation'] ?? null,
            'vendor_reference' => $data['vendor_reference'] ?? $order->vendor_reference,
            'estimated_delivery_date' => $data['estimated_delivery_date'] ?? $order->estimated_delivery_date,
        ]);

        return $order->fresh();
    }

    public function confirmDelivery(ThirdPartyOrder $order, string $date): ThirdPartyOrder
    {
        $order->update([
            'status' => ThirdPartyOrder::STATUS_DELIVERED,
            'actual_delivery_date' => $date,
        ]);

        return $order->fresh();
    }

    public function cancel(ThirdPartyOrder $order): ThirdPartyOrder
    {
        $order->update(['status' => ThirdPartyOrder::STATUS_CANCELLED]);
        return $order->fresh();
    }
}
