<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorAdvanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'request_number' => $this->request_number,
            'status' => $this->status,

            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn() => [
                'id' => $this->purchaseOrder->id,
                'order_number' => $this->purchaseOrder->order_number,
            ]),

            'requested_amount' => (float) $this->requested_amount,
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,

            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', fn() => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn() => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'approved_at' => $this->approved_at?->toIso8601String(),

            'paid_amount' => $this->getPaidAmount(),
            'remaining_amount' => $this->getRemainingAmount(),
            'can_be_approved' => $this->canBeApproved(),
            'can_be_paid' => $this->canBePaid(),

            'payments' => $this->whenLoaded('payments', fn() => $this->payments->map(fn($p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'payment_date' => $p->payment_date?->toDateString(),
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'reference' => $p->reference,
                'cleared_amount' => $p->getClearedAmount(),
                'uncleared_amount' => $p->getUnclearedAmount(),
                'is_fully_cleared' => $p->isFullyCleared(),
                'clearings' => $p->whenLoaded('clearings', fn() => $p->clearings->map(fn($c) => [
                    'id' => $c->id,
                    'bill_id' => $c->bill_id,
                    'cleared_amount' => (float) $c->cleared_amount,
                    'clearing_date' => $c->clearing_date?->toDateString(),
                ])->toArray()),
            ])->toArray()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
