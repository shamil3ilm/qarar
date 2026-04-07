<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqQuoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_quote_id' => $this->rfq_quote_id,
            'rfq_item_id' => $this->rfq_item_id,
            'rfq_item' => $this->whenLoaded('rfqItem', fn() => [
                'id' => $this->rfqItem->id,
                'description' => $this->rfqItem->description,
                'quantity' => (float) $this->rfqItem->quantity,
            ]),
            'unit_price' => (float) $this->unit_price,
            'quantity' => (float) $this->quantity,
            'discount_pct' => (float) $this->discount_pct,
            'tax_rate' => (float) $this->tax_rate,
            'line_total' => (float) $this->line_total,
            'delivery_days' => $this->delivery_days,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
