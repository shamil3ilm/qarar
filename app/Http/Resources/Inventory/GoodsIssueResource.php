<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'organization_id' => $this->organization_id,
            'gi_number'       => $this->gi_number,
            'gi_date'         => $this->gi_date?->toDateString(),
            'movement_type'   => $this->movement_type,
            'movement_type_label' => $this->getMovementTypeLabel(),
            'reference_type'  => $this->reference_type,
            'reference_id'    => $this->reference_id,
            'status'          => $this->status,
            'total_quantity'  => (float) $this->total_quantity,
            'total_value'     => (float) $this->total_value,
            'notes'           => $this->notes,
            'reversal_reason' => $this->reversal_reason,

            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),

            'branch' => $this->whenLoaded('branch', fn () => [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
            ]),

            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry'    => $this->whenLoaded('journalEntry', fn () => [
                'id'           => $this->journalEntry->id,
                'entry_number' => $this->journalEntry->entry_number ?? null,
                'entry_date'   => $this->journalEntry->entry_date?->toDateString(),
            ]),

            'lines' => $this->whenLoaded('lines', fn () =>
                $this->lines->map(fn ($line) => [
                    'id'            => $line->id,
                    'product_id'    => $line->product_id,
                    'product_name'  => $line->product?->name,
                    'product_sku'   => $line->product?->sku,
                    'variant_id'    => $line->variant_id,
                    'variant_name'  => $line->variant?->name,
                    'warehouse_id'  => $line->warehouse_id,
                    'location_id'   => $line->location_id,
                    'batch_id'      => $line->batch_id,
                    'unit_id'       => $line->unit_id,
                    'unit_name'     => $line->unit?->name,
                    'quantity'      => (float) $line->quantity,
                    'unit_cost'     => (float) $line->unit_cost,
                    'total_value'   => (float) $line->total_value,
                    'serial_number' => $line->serial_number,
                    'notes'         => $line->notes,
                ])
            ),

            'posted_at' => $this->posted_at?->toISOString(),
            'posted_by' => $this->whenLoaded('postedBy', fn () => [
                'id'   => $this->postedBy->id,
                'name' => $this->postedBy->name,
            ]),

            'reversed_at' => $this->reversed_at?->toISOString(),
            'reversed_by' => $this->whenLoaded('reversedBy', fn () => [
                'id'   => $this->reversedBy->id,
                'name' => $this->reversedBy->name,
            ]),

            'created_by' => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
