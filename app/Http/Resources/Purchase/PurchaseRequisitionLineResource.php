<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_id' => $this->requisition_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', fn() => $this->variant ? [
                'id' => $this->variant->id,
                'name' => $this->variant->name,
                'sku' => $this->variant->sku,
            ] : null),
            'quantity' => $this->quantity,
            'uom_id' => $this->uom_id,
            'estimated_unit_price' => $this->estimated_unit_price,
            'estimated_total' => $this->getEstimatedTotal(),
            'preferred_vendor_id' => $this->preferred_vendor_id,
            'preferred_vendor' => $this->whenLoaded('preferredVendor', fn() => $this->preferredVendor ? [
                'id' => $this->preferredVendor->id,
                'name' => $this->preferredVendor->display_name,
            ] : null),
            'warehouse_id' => $this->warehouse_id,
            'required_by_date' => $this->required_by_date?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
