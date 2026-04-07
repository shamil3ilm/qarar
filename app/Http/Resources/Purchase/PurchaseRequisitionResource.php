<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'requisition_number' => $this->requisition_number,
            'requisition_date' => $this->requisition_date?->toDateString(),
            'required_by_date' => $this->required_by_date?->toDateString(),
            'requisition_type' => $this->requisition_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', fn() => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn() => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'lines' => PurchaseRequisitionLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
