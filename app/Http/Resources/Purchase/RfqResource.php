<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'rfq_number' => $this->rfq_number,
            'title' => $this->title,
            'status' => $this->status,
            'submission_deadline' => $this->submission_deadline?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'delivery_address' => $this->delivery_address,
            'currency_code' => $this->currency_code,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'items' => RfqItemResource::collection($this->whenLoaded('items')),
            'vendors' => RfqVendorResource::collection($this->whenLoaded('vendors')),
            'quotes' => RfqQuoteResource::collection($this->whenLoaded('quotes')),
            'vendor_count' => $this->whenLoaded('vendors', fn() => $this->vendors->count()),
            'quote_count' => $this->whenLoaded('quotes', fn() => $this->quotes->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
