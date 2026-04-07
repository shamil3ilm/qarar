<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasingInfoRecordConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'uuid'                        => $this->uuid,
            'valid_from'                  => $this->valid_from?->toDateString(),
            'valid_to'                    => $this->valid_to?->toDateString(),
            'net_price'                   => (float) $this->net_price,
            'price_unit'                  => $this->price_unit,
            'currency_code'               => $this->currency_code,
            'discount_percent'            => (float) $this->discount_percent,
            'is_active'                   => (bool) $this->is_active,
            'created_at'                  => $this->created_at?->toIso8601String(),
        ];
    }
}
