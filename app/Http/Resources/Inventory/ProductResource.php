<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'barcode' => $this->barcode,
            'hsn_code' => $this->hsn_code,

            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'costing_method' => $this->costing_method,

            'has_variants' => $this->has_variants,
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,

            'reorder_level' => $this->reorder_level,
            'reorder_quantity' => $this->reorder_quantity,

            'weight' => $this->weight,
            'dimensions' => [
                'length' => $this->length ?? null,
                'width' => $this->width ?? null,
                'height' => $this->height ?? null,
            ],
            'image_url' => $this->image_url,

            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'tax_category_id' => $this->tax_category_id,

            'category' => $this->whenLoaded('category', fn() => new CategoryResource($this->category)),
            'unit' => $this->whenLoaded('unit', fn() => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),
            'tax_category' => $this->whenLoaded('taxCategory', fn() => [
                'id' => $this->taxCategory->id,
                'name' => $this->taxCategory->name,
                'code' => $this->taxCategory->code,
            ]),

            'variants' => $this->whenLoaded('variants', fn() =>
                $this->variants->map(fn($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'attribute_values' => $v->attribute_values,
                    'purchase_price' => $v->purchase_price,
                    'selling_price' => $v->selling_price,
                    'barcode' => $v->barcode,
                    'is_active' => $v->is_active,
                ])
            ),

            'stock_levels' => $this->whenLoaded('stockLevels', fn() =>
                $this->stockLevels->map(fn($s) => [
                    'warehouse_id' => $s->warehouse_id,
                    'warehouse_name' => $s->warehouse?->name,
                    'quantity' => $s->quantity,
                    'reserved_quantity' => $s->reserved_quantity,
                    'available_quantity' => $s->getAvailableQuantity(),
                    'average_cost' => $s->average_cost,
                    'total_value' => $s->total_value,
                ])
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
