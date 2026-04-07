<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'level' => $this->level,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'is_active' => $this->is_active,

            // Counts
            'active_employees_count' => $this->whenCounted('activeEmployees'),
            'employees_count' => $this->whenCounted('employees'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
