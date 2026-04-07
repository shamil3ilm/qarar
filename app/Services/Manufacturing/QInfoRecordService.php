<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\QInfoRecord;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QInfoRecordService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = QInfoRecord::with(['vendor', 'product', 'skipLotPlan'])
            ->where('organization_id', $orgId);

        if (isset($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['inspection_type'])) {
            $query->where('inspection_type', $filters['inspection_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): QInfoRecord
    {
        return QInfoRecord::create(array_merge($data, ['organization_id' => $orgId]));
    }

    public function update(QInfoRecord $record, array $data): QInfoRecord
    {
        $record->update($data);
        return $record->fresh();
    }

    public function deactivate(QInfoRecord $record): QInfoRecord
    {
        $record->update(['is_active' => false]);
        return $record->fresh();
    }

    public function getForGoodsReceipt(int $vendorId, int $productId, int $orgId): ?QInfoRecord
    {
        return QInfoRecord::where('organization_id', $orgId)
            ->where('vendor_id', $vendorId)
            ->where('product_id', $productId)
            ->where('inspection_type', QInfoRecord::INSPECTION_GOODS_RECEIPT)
            ->where('is_active', true)
            ->first();
    }

    public function getDueForInspection(int $orgId): Collection
    {
        return QInfoRecord::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('next_inspection_date')
                    ->orWhere('next_inspection_date', '<=', Carbon::today()->toDateString());
            })
            ->with(['vendor', 'product'])
            ->get();
    }

    public function updateAfterInspection(QInfoRecord $record, string $inspectionDate): void
    {
        $date = Carbon::parse($inspectionDate);
        $nextDate = $record->inspection_interval_days
            ? $date->copy()->addDays($record->inspection_interval_days)
            : null;

        $record->update([
            'last_inspection_date' => $date->toDateString(),
            'next_inspection_date' => $nextDate?->toDateString(),
        ]);
    }
}
