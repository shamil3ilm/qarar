<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\OutlineAgreement;
use App\Models\Purchase\OutlineAgreementItem;
use App\Models\Purchase\OutlineAgreementRelease;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class OutlineAgreementService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = OutlineAgreement::where('organization_id', $orgId)
            ->with(['vendor', 'items']);

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['agreement_type'])) {
            $query->where('agreement_type', $filters['agreement_type']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): OutlineAgreement
    {
        return OutlineAgreement::create(array_merge($data, [
            'organization_id' => $orgId,
            'created_by'      => Auth::id(),
        ]));
    }

    public function update(OutlineAgreement $agreement, array $data): OutlineAgreement
    {
        $agreement->update($data);

        return $agreement->fresh();
    }

    public function addItem(OutlineAgreement $agreement, array $data): OutlineAgreementItem
    {
        return OutlineAgreementItem::create(array_merge($data, [
            'organization_id'      => $agreement->organization_id,
            'outline_agreement_id' => $agreement->id,
        ]));
    }

    public function updateItem(OutlineAgreementItem $item, array $data): OutlineAgreementItem
    {
        $item->update($data);

        return $item->fresh();
    }

    public function createRelease(OutlineAgreement $agreement, array $data): OutlineAgreementRelease
    {
        $release = OutlineAgreementRelease::create(array_merge($data, [
            'organization_id'      => $agreement->organization_id,
            'outline_agreement_id' => $agreement->id,
        ]));

        // Update released totals on agreement
        $releasedQty = (string) ($data['release_quantity'] ?? '0');
        $releasedVal = (string) ($data['release_value'] ?? '0');

        $agreement->increment('released_quantity', (float) $releasedQty);
        $agreement->increment('released_value', (float) $releasedVal);

        // Update item if specified
        if (!empty($data['outline_agreement_item_id'])) {
            $item = OutlineAgreementItem::find($data['outline_agreement_item_id']);
            if ($item) {
                $item->increment('released_quantity', (float) $releasedQty);
                $item->increment('released_value', (float) $releasedVal);
            }
        }

        return $release;
    }

    public function activate(OutlineAgreement $agreement): OutlineAgreement
    {
        $agreement->update(['status' => OutlineAgreement::STATUS_ACTIVE]);

        return $agreement->fresh();
    }

    public function expire(OutlineAgreement $agreement): OutlineAgreement
    {
        $agreement->update(['status' => OutlineAgreement::STATUS_EXPIRED]);

        return $agreement->fresh();
    }

    public function cancel(OutlineAgreement $agreement): OutlineAgreement
    {
        $agreement->update(['status' => OutlineAgreement::STATUS_CANCELLED]);

        return $agreement->fresh();
    }
}
