<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBudgetSupplementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'supplement_type'  => $this->supplement_type,
            'amount'           => (float) $this->amount,
            'reason'           => $this->reason,
            'reference_number' => $this->reference_number,
            'status'           => $this->status,
            'approved_at'      => $this->approved_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
