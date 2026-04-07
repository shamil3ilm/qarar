<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'description' => $this->description,
            'quantity' => $this->quantity !== null ? (float) $this->quantity : null,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'line_total' => $this->line_total !== null ? (float) $this->line_total : null,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn() => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'abbreviation' => $this->unit->abbreviation,
            ]),
            'delivery_schedule' => $this->delivery_schedule,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
