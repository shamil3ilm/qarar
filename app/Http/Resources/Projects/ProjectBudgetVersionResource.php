<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBudgetVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'project_id'   => $this->project_id,
            'version_code' => $this->version_code,
            'version_name' => $this->version_name,
            'fiscal_year'  => $this->fiscal_year,
            'status'       => $this->status,
            'is_current'   => (bool) $this->is_current,
            'total_budget' => (float) $this->total_budget,
            'approved_at'  => $this->approved_at?->toIso8601String(),
            'notes'        => $this->notes,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),

            'line_items'  => ProjectBudgetLineItemResource::collection($this->whenLoaded('lineItems')),
            'supplements' => ProjectBudgetSupplementResource::collection($this->whenLoaded('supplements')),
        ];
    }
}
