<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingConditionRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condition_type_id' => $this->condition_type_id,
            'condition_type' => $this->whenLoaded('conditionType', fn() => [
                'id' => $this->conditionType->id,
                'code' => $this->conditionType->code,
                'name' => $this->conditionType->name,
                'condition_class' => $this->conditionType->condition_class,
                'calculation_type' => $this->conditionType->calculation_type,
            ]),
            'key_combination' => $this->key_combination,
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'price_list_id' => $this->price_list_id,
            'rate' => $this->rate,
            'currency_code' => $this->currency_code,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
