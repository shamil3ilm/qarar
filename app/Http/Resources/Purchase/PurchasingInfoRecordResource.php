<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasingInfoRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'uuid'                       => $this->uuid,
            'vendor_id'                  => $this->vendor_id,
            'product_id'                 => $this->product_id,
            'warehouse_id'               => $this->warehouse_id,
            'info_category'              => $this->info_category,
            'is_active'                  => (bool) $this->is_active,
            'planned_delivery_days'      => $this->planned_delivery_days,
            'reminder_days'              => $this->reminder_days,
            'overdelivery_tolerance'     => $this->overdelivery_tolerance !== null
                ? (float) $this->overdelivery_tolerance : null,
            'underdelivery_tolerance'    => $this->underdelivery_tolerance !== null
                ? (float) $this->underdelivery_tolerance : null,
            'is_underdelivery_tolerated' => (bool) $this->is_underdelivery_tolerated,
            'net_price'                  => $this->net_price !== null
                ? (float) $this->net_price : null,
            'price_unit'                 => $this->price_unit,
            'currency_code'              => $this->currency_code,
            'minimum_order_quantity'     => $this->minimum_order_quantity !== null
                ? (float) $this->minimum_order_quantity : null,
            'standard_order_quantity'    => $this->standard_order_quantity !== null
                ? (float) $this->standard_order_quantity : null,
            'last_purchase_date'         => $this->last_purchase_date?->toDateString(),
            'last_purchase_price'        => $this->last_purchase_price !== null
                ? (float) $this->last_purchase_price : null,
            'effective_price'            => $this->getEffectivePrice() !== null
                ? (float) $this->getEffectivePrice() : null,
            'notes'                      => $this->notes,

            'conditions' => $this->whenLoaded(
                'conditions',
                fn () => PurchasingInfoRecordConditionResource::collection($this->conditions)
            ),

            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id'   => $this->vendor->id,
                'name' => $this->vendor->getDisplayName(),
            ]),

            'product' => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
