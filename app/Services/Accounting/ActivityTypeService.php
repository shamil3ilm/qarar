<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\ActivityRate;
use App\Models\Accounting\ActivityType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivityTypeService
{
    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function index(array $filters): LengthAwarePaginator
    {
        $query = ActivityType::query()->orderBy('code');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['cost_element_id'])) {
            $query->where('cost_element_id', (int) $filters['cost_element_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->with('costElement:id,code,name')->paginate($perPage);
    }

    public function store(array $data): ActivityType
    {
        return DB::transaction(function () use ($data): ActivityType {
            return ActivityType::create($data);
        });
    }

    public function update(ActivityType $activityType, array $data): ActivityType
    {
        return DB::transaction(function () use ($activityType, $data): ActivityType {
            $activityType->update($data);

            return $activityType->fresh(['costElement:id,code,name']);
        });
    }

    public function destroy(ActivityType $activityType): void
    {
        $activityType->delete();
    }

    // ----------------------------------------------------------------
    // Rate Management
    // ----------------------------------------------------------------

    /**
     * Upsert an activity rate for a given activity type / cost center / fiscal year / period.
     */
    public function setRate(ActivityType $activityType, array $data): ActivityRate
    {
        return DB::transaction(function () use ($activityType, $data): ActivityRate {
            $period = (int) $data['period'];

            if ($period < 1 || $period > 12) {
                throw new InvalidArgumentException(
                    'Period must be between 1 and 12.'
                );
            }

            /** @var ActivityRate $rate */
            $rate = ActivityRate::updateOrCreate(
                [
                    'activity_type_id' => $activityType->id,
                    'cost_center_id'   => (int) $data['cost_center_id'],
                    'fiscal_year_id'   => (int) $data['fiscal_year_id'],
                    'period'           => $period,
                ],
                [
                    'planned_rate'  => $data['planned_rate'] ?? 0,
                    'actual_rate'   => $data['actual_rate'] ?? 0,
                    'currency_code' => $data['currency_code'] ?? 'SAR',
                ]
            );

            return $rate->load(['activityType:id,code,name', 'costCenter:id,code,name', 'fiscalYear:id,name']);
        });
    }

    /**
     * Confirm the planned rate for a given period, recording the user who confirmed.
     * Corresponds to SAP KP26 "rate confirmation" workflow.
     */
    public function confirmRate(ActivityType $activityType, array $data, int $confirmedByUserId): ActivityRate
    {
        return DB::transaction(function () use ($activityType, $data, $confirmedByUserId): ActivityRate {
            $period = (int) ($data['period'] ?? 1);

            if ($period < 1 || $period > 12) {
                throw new InvalidArgumentException('Period must be between 1 and 12.');
            }

            /** @var ActivityRate $rate */
            $rate = ActivityRate::where('activity_type_id', $activityType->id)
                ->where('cost_center_id', (int) $data['cost_center_id'])
                ->where('fiscal_year_id', (int) $data['fiscal_year_id'])
                ->where('period', $period)
                ->firstOrFail();

            if ($rate->is_confirmed) {
                throw new InvalidArgumentException(
                    "Rate for period {$period} is already confirmed."
                );
            }

            $rate->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
                'confirmed_by' => $confirmedByUserId,
            ]);

            return $rate->fresh();
        });
    }

    /**
     * Calculate plan vs. actual variance for an activity type across all periods.
     *
     * @return array{
     *   activity_type_id: int,
     *   fiscal_year_id: int,
     *   cost_center_id: int,
     *   periods: list<array{period: int, planned_rate: float, actual_rate: float, variance_amount: float, variance_percent: float}>,
     *   total_variance: float,
     * }
     */
    public function calculateVariance(ActivityType $activityType, array $data): array
    {
        $rates = ActivityRate::where('activity_type_id', $activityType->id)
            ->where('cost_center_id', (int) $data['cost_center_id'])
            ->where('fiscal_year_id', (int) $data['fiscal_year_id'])
            ->orderBy('period')
            ->get();

        $periods       = [];
        $totalVariance = 0.0;

        foreach ($rates as $rate) {
            $variance        = $rate->varianceAmount();
            $totalVariance  += $variance;

            $periods[] = [
                'period'           => $rate->period,
                'planned_rate'     => (float) $rate->planned_rate,
                'actual_rate'      => (float) $rate->actual_rate,
                'variance_amount'  => $variance,
                'variance_percent' => $rate->variancePercent(),
                'is_confirmed'     => $rate->is_confirmed,
            ];
        }

        return [
            'activity_type_id' => $activityType->id,
            'fiscal_year_id'   => (int) $data['fiscal_year_id'],
            'cost_center_id'   => (int) $data['cost_center_id'],
            'periods'          => $periods,
            'total_variance'   => round($totalVariance, 4),
        ];
    }
}
