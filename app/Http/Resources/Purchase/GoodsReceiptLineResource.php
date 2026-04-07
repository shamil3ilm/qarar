<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gr_id' => $this->gr_id,
            'po_line_id' => $this->po_line_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'variant_id' => $this->variant_id,
            'description' => $this->description,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_rejected' => (float) $this->quantity_rejected,
            'accepted_quantity' => $this->getAcceptedQuantity(),
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn() => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'abbreviation' => $this->unit->abbreviation,
            ]),
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'location_id' => $this->location_id,
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
