<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhysicalInventoryDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'document_number' => $this->document_number,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn() => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'count_date' => $this->count_date?->toDateString(),
            'inventory_type' => $this->inventory_type,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn() => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'counted_at' => $this->counted_at?->toIso8601String(),
            'posted_by' => $this->posted_by,
            'poster' => $this->whenLoaded('poster', fn() => $this->poster ? [
                'id' => $this->poster->id,
                'name' => $this->poster->name,
            ] : null),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines_count' => $this->whenLoaded('lines', fn() => $this->lines->count()),
            'total_difference_value' => $this->getTotalDifferenceValue(),
            'lines' => PhysicalInventoryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
