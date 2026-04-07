<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotaArrangementItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'uuid'                        => $this->uuid,
            'vendor_id'                   => $this->vendor_id,
            'purchasing_info_record_id'   => $this->purchasing_info_record_id,
            'quota_percentage'            => (float) $this->quota_percentage,
            'min_lot_size'                => $this->min_lot_size !== null
                ? (float) $this->min_lot_size : null,
            'max_lot_size'                => $this->max_lot_size !== null
                ? (float) $this->max_lot_size : null,
            'allocated_quantity'          => (float) $this->allocated_quantity,
            'quota_rating'                => $this->getQuotaRating(),
            'last_assigned_at'            => $this->last_assigned_at?->toIso8601String(),
            'is_blocked'                  => (bool) $this->is_blocked,

            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id'   => $this->vendor->id,
                'name' => $this->vendor->getDisplayName(),
            ]),
        ];
    }
}
