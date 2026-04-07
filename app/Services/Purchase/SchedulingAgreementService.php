<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\SaDeliverySchedule;
use App\Models\Purchase\SchedulingAgreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SchedulingAgreementService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = SchedulingAgreement::where('organization_id', $orgId)
            ->with(['vendor', 'product']);

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): SchedulingAgreement
    {
        return SchedulingAgreement::create(array_merge($data, [
            'organization_id' => $orgId,
        ]));
    }

    public function update(SchedulingAgreement $agreement, array $data): SchedulingAgreement
    {
        $agreement->update($data);

        return $agreement->fresh();
    }

    public function addScheduleLine(SchedulingAgreement $agreement, array $data): SaDeliverySchedule
    {
        return SaDeliverySchedule::create(array_merge($data, [
            'organization_id'        => $agreement->organization_id,
            'scheduling_agreement_id' => $agreement->id,
        ]));
    }

    public function updateScheduleLine(SaDeliverySchedule $line, array $data): SaDeliverySchedule
    {
        $line->update($data);

        return $line->fresh();
    }

    public function receiveDelivery(SaDeliverySchedule $line, float $quantity): void
    {
        $newReceived = bcadd((string) $line->received_quantity, (string) $quantity, 4);
        $line->received_quantity = $newReceived;

        if (bccomp($newReceived, (string) $line->scheduled_quantity, 4) >= 0) {
            $line->status = SaDeliverySchedule::STATUS_COMPLETE;
        } else {
            $line->status = SaDeliverySchedule::STATUS_PARTIAL;
        }

        $line->save();

        // Update agreement released_quantity
        $agreement = $line->schedulingAgreement;
        $agreement->increment('released_quantity', $quantity);
    }

    /**
     * Match MRP requirements to active scheduling agreements and generate schedule lines.
     *
     * @param  int    $productId
     * @param  array  $requirements  Each entry: ['date' => 'Y-m-d', 'quantity' => float, 'organization_id' => int]
     * @return array  Created SaDeliverySchedule records
     */
    public function generateFromMrp(int $productId, array $requirements): array
    {
        $created = [];

        foreach ($requirements as $req) {
            $orgId = (int) ($req['organization_id'] ?? 0);

            $agreement = SchedulingAgreement::where('organization_id', $orgId)
                ->where('product_id', $productId)
                ->where('status', SchedulingAgreement::STATUS_ACTIVE)
                ->where('valid_from', '<=', $req['date'])
                ->where(function ($q) use ($req) {
                    $q->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $req['date']);
                })
                ->first();

            if ($agreement === null) {
                continue;
            }

            $created[] = $this->addScheduleLine($agreement, [
                'schedule_date'      => $req['date'],
                'scheduled_quantity' => $req['quantity'],
            ]);
        }

        return $created;
    }
}
