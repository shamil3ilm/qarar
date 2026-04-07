<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\StatisticalKeyFigure;
use App\Models\Accounting\StatisticalKeyFigureValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StatisticalKeyFigureService
{
    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = StatisticalKeyFigure::orderBy('code');

        if (!empty($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): StatisticalKeyFigure
    {
        return DB::transaction(function () use ($data): StatisticalKeyFigure {
            $this->validateUniqueCode(
                (int) $data['organization_id'],
                $data['code']
            );

            return StatisticalKeyFigure::create($data);
        });
    }

    public function update(StatisticalKeyFigure $skf, array $data): StatisticalKeyFigure
    {
        return DB::transaction(function () use ($skf, $data): StatisticalKeyFigure {
            if (isset($data['code']) && $data['code'] !== $skf->code) {
                $this->validateUniqueCode($skf->organization_id, $data['code'], $skf->id);
            }

            $skf->update($data);

            return $skf->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Values
    // ----------------------------------------------------------------

    public function postValue(array $data): StatisticalKeyFigureValue
    {
        return DB::transaction(function () use ($data): StatisticalKeyFigureValue {
            $period = (int) $data['period'];

            if ($period < 1 || $period > 12) {
                throw new InvalidArgumentException('period must be between 1 and 12.');
            }

            // Upsert: update if the unique combination already exists
            $existing = StatisticalKeyFigureValue::withoutGlobalScope('organization')
                ->where('organization_id', $data['organization_id'])
                ->where('statistical_key_figure_id', $data['statistical_key_figure_id'])
                ->where('cost_center_id', $data['cost_center_id'] ?? null)
                ->where('profit_center_id', $data['profit_center_id'] ?? null)
                ->where('period', $data['period'])
                ->where('fiscal_year', $data['fiscal_year'])
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'value'     => $data['value'],
                    'posted_by' => $data['posted_by'] ?? null,
                ]);

                return $existing->fresh();
            }

            return StatisticalKeyFigureValue::create($data);
        });
    }

    public function getValuesForPeriod(int $period, int $year): Collection
    {
        return StatisticalKeyFigureValue::with(['statisticalKeyFigure', 'costCenter', 'profitCenter'])
            ->where('period', $period)
            ->where('fiscal_year', $year)
            ->get();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function validateUniqueCode(int $orgId, string $code, ?int $excludeId = null): void
    {
        $query = StatisticalKeyFigure::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "A statistical key figure with code [{$code}] already exists in this organization."
            );
        }
    }
}
