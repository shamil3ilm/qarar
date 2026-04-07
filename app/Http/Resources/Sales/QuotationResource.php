<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'branch_id' => $this->branch_id,
            'quotation_number' => $this->quotation_number,
            'status' => $this->status,

            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->getDisplayName(),
                'email' => $this->customer->email,
            ]),

            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,

            'quotation_date' => $this->quotation_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),

            'currency_code' => $this->currency_code,
            'exchange_rate' => $this->exchange_rate,

            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,

            'salesperson' => $this->whenLoaded('salesperson', fn () => [
                'id' => $this->salesperson->id,
                'name' => $this->salesperson->name,
            ]),

            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,
            'reference' => $this->reference,
            'version' => $this->version,

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount_type' => $line->discount_type,
                'discount_value' => $line->discount_value,
                'discount_amount' => $line->discount_amount,
                'tax_rate' => $line->tax_rate,
                'tax_amount' => $line->tax_amount,
                'subtotal' => $line->subtotal,
                'total' => $line->total,
            ])),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
