<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntercompanySalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'uuid'                      => $this->uuid,
            'selling_organization_id'   => $this->selling_organization_id,
            'buying_organization_id'    => $this->buying_organization_id,
            'sales_order_id'            => $this->sales_order_id,
            'order_number'              => $this->order_number,
            'status'                    => $this->status,
            'order_date'                => $this->order_date?->toDateString(),
            'requested_delivery_date'   => $this->requested_delivery_date?->toDateString(),
            'currency_code'             => $this->currency_code,
            'subtotal'                  => (float) $this->subtotal,
            'tax_amount'                => (float) $this->tax_amount,
            'total_amount'              => (float) $this->total_amount,
            'notes'                     => $this->notes,
            'created_by'                => $this->created_by,
            'created_at'                => $this->created_at?->toIso8601String(),
            'updated_at'                => $this->updated_at?->toIso8601String(),

            'lines' => $this->whenLoaded('lines', fn () =>
                $this->lines->map(fn ($line) => [
                    'id'                           => $line->id,
                    'uuid'                         => $line->uuid,
                    'product_id'                   => $line->product_id,
                    'line_number'                  => $line->line_number,
                    'description'                  => $line->description,
                    'quantity'                     => (float) $line->quantity,
                    'unit_of_measure'              => $line->unit_of_measure,
                    'transfer_price'               => (float) $line->transfer_price,
                    'list_price'                   => $line->list_price !== null ? (float) $line->list_price : null,
                    'tax_rate'                     => (float) $line->tax_rate,
                    'tax_amount'                   => (float) $line->tax_amount,
                    'line_total'                   => (float) $line->line_total,
                    'delivered_quantity'           => (float) $line->delivered_quantity,
                    'billed_quantity'              => (float) $line->billed_quantity,
                ])
            ),

            'purchase_order_link' => $this->whenLoaded('purchaseOrderLink', fn () => [
                'id'                           => $this->purchaseOrderLink->id,
                'purchase_order_id'            => $this->purchaseOrderLink->purchase_order_id,
                'buying_organization_id'       => $this->purchaseOrderLink->buying_organization_id,
                'status'                       => $this->purchaseOrderLink->status,
            ]),

            'billing_documents' => $this->whenLoaded('billingDocuments', fn () =>
                IcBillingDocumentResource::collection($this->billingDocuments)
            ),
        ];
    }
}
