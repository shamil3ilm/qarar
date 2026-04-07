<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'contract_number' => $this->contract_number,
            'contract_type' => $this->contract_type,
            'title' => $this->title,
            'status' => $this->status,

            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'signed_date' => $this->signed_date?->toDateString(),
            'auto_renew' => $this->auto_renew,
            'renewal_notice_days' => $this->renewal_notice_days,

            'currency_code' => $this->currency_code,
            'total_value' => $this->total_value !== null ? (float) $this->total_value : null,
            'billed_amount' => (float) $this->billed_amount,
            'remaining_value' => $this->getRemainingValue(),

            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'parent_contract_id' => $this->parent_contract_id,

            'parent_contract' => $this->whenLoaded('parentContract', fn() => [
                'id' => $this->parentContract->id,
                'contract_number' => $this->parentContract->contract_number,
                'title' => $this->parentContract->title,
            ]),

            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'lines' => ContractLineResource::collection($this->whenLoaded('lines')),
            'milestones' => $this->whenLoaded('milestones', fn() => $this->milestones->map(fn($m) => [
                'id' => $m->id,
                'milestone_name' => $m->milestone_name,
                'due_date' => $m->due_date?->toDateString(),
                'amount' => (float) $m->amount,
                'status' => $m->status,
                'invoice_id' => $m->invoice_id,
                'notes' => $m->notes,
                'is_overdue' => $m->isOverdue(),
            ])->toArray()),
            'releases' => $this->whenLoaded('releases', fn() => $this->releases->map(fn($r) => [
                'id' => $r->id,
                'release_date' => $r->release_date?->toDateString(),
                'amount' => (float) $r->amount,
                'status' => $r->status,
                'source_type' => $r->source_type,
                'source_id' => $r->source_id,
            ])->toArray()),
            'documents' => $this->whenLoaded('documents', fn() => $this->documents->map(fn($d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'file_path' => $d->file_path,
                'uploaded_at' => $d->uploaded_at?->toIso8601String(),
            ])->toArray()),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
