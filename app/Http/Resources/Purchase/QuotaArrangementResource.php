<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotaArrangementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'product_id'       => $this->product_id,
            'warehouse_id'     => $this->warehouse_id,
            'valid_from'       => $this->valid_from?->toDateString(),
            'valid_to'         => $this->valid_to?->toDateString(),
            'is_active'        => (bool) $this->is_active,
            'notes'            => $this->notes,
            'total_percentage' => $this->getTotalPercentage(),

            'items' => $this->whenLoaded(
                'items',
                fn () => QuotaArrangementItemResource::collection($this->items)
            ),

            'product' => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
