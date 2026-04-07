<?php

declare(strict_types=1);

namespace App\Http\Resources\Manufacturing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnsInspectionDefectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'defect_code'         => $this->defect_code,
            'defect_description'  => $this->defect_description,
            'severity'            => $this->severity,
            'quantity_affected'   => (float) $this->quantity_affected,
            'recommended_action'  => $this->recommended_action,
            'actual_action_taken' => $this->actual_action_taken,
            'notes'               => $this->notes,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
