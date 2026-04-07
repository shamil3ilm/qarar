<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhysicalInventoryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
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
            'warehouse_location_id' => $this->warehouse_location_id,
            'warehouse_location' => $this->whenLoaded('warehouseLocation', fn() => $this->warehouseLocation ? [
                'id' => $this->warehouseLocation->id,
                'code' => $this->warehouseLocation->code,
                'name' => $this->warehouseLocation->name,
            ] : null),
            'book_quantity' => $this->book_quantity,
            'counted_quantity' => $this->counted_quantity,
            'difference_quantity' => $this->difference_quantity,
            'unit_cost' => $this->unit_cost,
            'difference_value' => $this->difference_value,
            'adjustment_status' => $this->adjustment_status,
            'has_difference' => $this->hasDifference(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
