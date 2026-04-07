<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingProcedureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'condition_types' => PricingConditionTypeResource::collection($this->whenLoaded('conditionTypes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
