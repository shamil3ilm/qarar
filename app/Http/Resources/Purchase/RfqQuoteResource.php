<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'rfq_id' => $this->rfq_id,
            'rfq_vendor_id' => $this->rfq_vendor_id,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn() => [
                'id' => $this->contact->id,
                'name' => $this->contact->getDisplayName(),
            ]),
            'quote_number' => $this->quote_number,
            'quote_date' => $this->quote_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'currency_code' => $this->currency_code,
            'total_amount' => (float) $this->total_amount,
            'delivery_days' => $this->delivery_days,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'status' => $this->status,
            'lines' => RfqQuoteLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
