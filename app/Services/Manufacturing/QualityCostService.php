<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\QualityCostEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QualityCostService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = QualityCostEntry::with(['product', 'recorder'])
            ->where('organization_id', $orgId);

        if (isset($filters['cost_category'])) {
            $query->where('cost_category', $filters['cost_category']);
        }

        if (isset($filters['period']) && isset($filters['fiscal_year'])) {
            $query->where('period', $filters['period'])->where('fiscal_year', $filters['fiscal_year']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): QualityCostEntry
    {
        return QualityCostEntry::create(array_merge($data, ['organization_id' => $orgId]));
    }

    public function update(QualityCostEntry $entry, array $data): QualityCostEntry
    {
        $entry->update($data);
        return $entry->fresh();
    }

    public function delete(QualityCostEntry $entry): void
    {
        $entry->delete();
    }

    public function getSummary(int $period, int $year, int $orgId): array
    {
        $rows = QualityCostEntry::where('organization_id', $orgId)
            ->where('period', $period)
            ->where('fiscal_year', $year)
            ->whereNull('deleted_at')
            ->select('cost_category', DB::raw('SUM(amount) as total'))
            ->groupBy('cost_category')
            ->get()
            ->keyBy('cost_category');

        $categories = [
            QualityCostEntry::CATEGORY_PREVENTION,
            QualityCostEntry::CATEGORY_APPRAISAL,
            QualityCostEntry::CATEGORY_INTERNAL_FAILURE,
            QualityCostEntry::CATEGORY_EXTERNAL_FAILURE,
        ];

        $summary = [];
        $grandTotal = '0.0000';

        foreach ($categories as $category) {
            $total = isset($rows[$category]) ? (string) $rows[$category]->total : '0.0000';
            $summary[$category] = $total;
            $grandTotal = bcadd($grandTotal, $total, 4);
        }

        return [
            'period'      => $period,
            'fiscal_year' => $year,
            'by_category' => $summary,
            'total'       => $grandTotal,
        ];
    }

    public function getTrend(int $months, int $orgId): array
    {
        $result = [];
        $now = Carbon::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $period = (int) $date->format('n');
            $year   = (int) $date->format('Y');

            $rows = QualityCostEntry::where('organization_id', $orgId)
                ->where('period', $period)
                ->where('fiscal_year', $year)
                ->whereNull('deleted_at')
                ->select('cost_category', DB::raw('SUM(amount) as total'))
                ->groupBy('cost_category')
                ->get()
                ->keyBy('cost_category');

            $entry = [
                'period'      => $period,
                'fiscal_year' => $year,
                'label'       => $date->format('M Y'),
            ];

            foreach ([
                QualityCostEntry::CATEGORY_PREVENTION,
                QualityCostEntry::CATEGORY_APPRAISAL,
                QualityCostEntry::CATEGORY_INTERNAL_FAILURE,
                QualityCostEntry::CATEGORY_EXTERNAL_FAILURE,
            ] as $cat) {
                $entry[$cat] = isset($rows[$cat]) ? (string) $rows[$cat]->total : '0.0000';
            }

            $result[] = $entry;
        }

        return $result;
    }
}
