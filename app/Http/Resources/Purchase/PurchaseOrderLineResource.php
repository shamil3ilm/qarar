<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,

            // Product info
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ]),
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', fn() => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'name' => $this->variant->name,
            ]),
            'description' => $this->description,

            // Quantities
            'quantity' => (float) $this->quantity,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_billed' => (float) $this->quantity_billed,
            'remaining_to_receive' => $this->getRemainingToReceive(),
            'remaining_to_bill' => $this->getRemainingToBill(),
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn() => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),

            // Pricing
            'unit_price' => (float) $this->unit_price,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,

            // Tax
            'tax_category_id' => $this->tax_category_id,
            'tax_category' => $this->whenLoaded('taxCategory', fn() => [
                'id' => $this->taxCategory->id,
                'name' => $this->taxCategory->name,
                'code' => $this->taxCategory->code,
            ]),
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,

            // Totals
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,

            // Other
            'warehouse_id' => $this->warehouse_id,
            'line_order' => $this->line_order,
        ];
    }
}
