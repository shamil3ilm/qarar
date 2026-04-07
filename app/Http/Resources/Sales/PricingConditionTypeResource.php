<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingConditionTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'condition_class' => $this->condition_class,
            'calculation_type' => $this->calculation_type,
            'is_mandatory' => $this->is_mandatory,
            'step' => $this->step,
            'counter' => $this->counter,
            'records_count' => $this->whenLoaded('records', fn() => $this->records->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
