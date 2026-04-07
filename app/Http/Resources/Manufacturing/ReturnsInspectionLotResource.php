<?php

declare(strict_types=1);

namespace App\Http\Resources\Manufacturing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnsInspectionLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'uuid'                 => $this->uuid,
            'rma_request_id'       => $this->rma_request_id,
            'sales_return_id'      => $this->sales_return_id,
            'purchase_return_id'   => $this->purchase_return_id,
            'product_id'           => $this->product_id,
            'lot_number'           => $this->lot_number,
            'return_type'          => $this->return_type,
            'status'               => $this->status,

            'received_quantity'    => (float) $this->received_quantity,
            'inspected_quantity'   => (float) $this->inspected_quantity,
            'accepted_quantity'    => (float) $this->accepted_quantity,
            'rejected_quantity'    => (float) $this->rejected_quantity,
            'rework_quantity'      => (float) $this->rework_quantity,
            'unaccounted_quantity' => (float) $this->getUnaccountedQuantity(),

            'usage_decision'       => $this->usage_decision,
            'usage_decision_at'    => $this->usage_decision_at?->toIso8601String(),
            'usage_decision_notes' => $this->usage_decision_notes,

            'inspection_start_date' => $this->inspection_start_date?->format('Y-m-d'),
            'inspection_end_date'   => $this->inspection_end_date?->format('Y-m-d'),

            'stock_posted'    => (bool) $this->stock_posted,
            'stock_posted_at' => $this->stock_posted_at?->toIso8601String(),

            'total_defects' => $this->getTotalDefectCount(),

            'defects' => ReturnsInspectionDefectResource::collection(
                $this->whenLoaded('defects')
            ),

            'product' => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
