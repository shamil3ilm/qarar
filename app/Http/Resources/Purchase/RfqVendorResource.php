<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqVendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn() => [
                'id' => $this->contact->id,
                'name' => $this->contact->getDisplayName(),
                'email' => $this->contact->email,
            ]),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'response_deadline' => $this->response_deadline?->toDateString(),
            'status' => $this->status,
            'quotes' => RfqQuoteResource::collection($this->whenLoaded('quotes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
