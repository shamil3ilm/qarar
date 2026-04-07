<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'gr_number' => $this->gr_number,
            'status' => $this->status,

            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn() => [
                'id' => $this->purchaseOrder->id,
                'order_number' => $this->purchaseOrder->order_number,
            ]),

            'contact_id' => $this->contact_id,
            'vendor' => $this->whenLoaded('vendor', fn() => [
                'id' => $this->vendor->id,
                'name' => $this->vendor->getDisplayName(),
            ]),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn() => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),

            'gr_date' => $this->gr_date?->toDateString(),
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,

            'reversal_reason' => $this->reversal_reason,
            'reversed_at' => $this->reversed_at?->toIso8601String(),

            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry' => $this->whenLoaded('journalEntry', fn() => [
                'id' => $this->journalEntry->id,
                'entry_number' => $this->journalEntry->entry_number ?? null,
            ]),

            'total_cost' => $this->getTotalCost(),
            'can_be_posted' => $this->canBePosted(),
            'can_be_reversed' => $this->canBeReversed(),

            'lines' => GoodsReceiptLineResource::collection($this->whenLoaded('lines')),

            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
