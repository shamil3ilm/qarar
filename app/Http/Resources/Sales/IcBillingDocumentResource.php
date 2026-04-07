<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IcBillingDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'document_number' => $this->document_number,
            'billing_date'    => $this->billing_date?->toDateString(),
            'currency_code'   => $this->currency_code,
            'subtotal'        => (float) $this->subtotal,
            'tax_amount'      => (float) $this->tax_amount,
            'total_amount'    => (float) $this->total_amount,
            'status'          => $this->status,
            'posted_at'       => $this->posted_at?->toIso8601String(),
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
