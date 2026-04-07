<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'manager_id' => $this->manager_id,
            'cost_center_id' => $this->cost_center_id,
            'is_active' => $this->is_active,

            // Relations
            'parent' => $this->whenLoaded('parent', fn () => new DepartmentResource($this->parent)),
            'children' => $this->whenLoaded('children', fn () => DepartmentResource::collection($this->children)),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ]),

            // Counts
            'active_employees_count' => $this->whenCounted('activeEmployees'),
            'employees_count' => $this->whenCounted('employees'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
