<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\StabilityStudy;
use App\Models\Manufacturing\StabilityStudyResult;
use App\Models\Manufacturing\StabilityStudyTimePoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class StabilityStudyService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = StabilityStudy::with(['product', 'inventoryBatch'])
            ->where('organization_id', $orgId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['study_type'])) {
            $query->where('study_type', $filters['study_type']);
        }

        return $query->orderByDesc('start_date')->paginate($filters['per_page'] ?? 20);
    }

    public function create(int $orgId, array $data): StabilityStudy
    {
        return StabilityStudy::create(array_merge($data, [
            'organization_id' => $orgId,
            'status'          => StabilityStudy::STATUS_PLANNED,
        ]));
    }

    public function update(StabilityStudy $study, array $data): StabilityStudy
    {
        if ($study->status === StabilityStudy::STATUS_COMPLETED) {
            throw new RuntimeException('Completed studies cannot be modified.');
        }

        $study->update($data);
        return $study->fresh();
    }

    public function activate(StabilityStudy $study): StabilityStudy
    {
        if ($study->status !== StabilityStudy::STATUS_PLANNED) {
            throw new RuntimeException('Only planned studies can be activated.');
        }

        $study->update(['status' => StabilityStudy::STATUS_ACTIVE]);
        return $study->fresh();
    }

    public function complete(StabilityStudy $study): StabilityStudy
    {
        if ($study->status !== StabilityStudy::STATUS_ACTIVE) {
            throw new RuntimeException('Only active studies can be completed.');
        }

        $study->update(['status' => StabilityStudy::STATUS_COMPLETED]);
        return $study->fresh();
    }

    public function addTimePoint(StabilityStudy $study, array $data): StabilityStudyTimePoint
    {
        return StabilityStudyTimePoint::create(array_merge($data, [
            'organization_id'    => $study->organization_id,
            'stability_study_id' => $study->id,
            'status'             => StabilityStudyTimePoint::STATUS_SCHEDULED,
        ]));
    }

    public function updateTimePoint(StabilityStudyTimePoint $timePoint, array $data): StabilityStudyTimePoint
    {
        $timePoint->update($data);
        return $timePoint->fresh();
    }

    public function addResult(StabilityStudyTimePoint $timePoint, array $data): StabilityStudyResult
    {
        // Auto-determine pass/fail if specs and value are provided
        $isPass = null;

        if (isset($data['result_value'])) {
            $value = (float) $data['result_value'];
            $min   = isset($data['specification_min']) ? (float) $data['specification_min'] : null;
            $max   = isset($data['specification_max']) ? (float) $data['specification_max'] : null;

            if ($min !== null || $max !== null) {
                $isPass = true;
                if ($min !== null && $value < $min) {
                    $isPass = false;
                }
                if ($max !== null && $value > $max) {
                    $isPass = false;
                }
            }
        }

        if (isset($data['is_pass'])) {
            $isPass = (bool) $data['is_pass'];
        }

        return StabilityStudyResult::create(array_merge($data, [
            'organization_id'               => $timePoint->organization_id,
            'stability_study_time_point_id' => $timePoint->id,
            'is_pass'                       => $isPass,
        ]));
    }

    public function getStudySummary(int $studyId, int $orgId): array
    {
        $study = StabilityStudy::with(['product', 'inventoryBatch', 'timePoints.results'])
            ->where('organization_id', $orgId)
            ->findOrFail($studyId);

        $timePointSummaries = $study->timePoints->map(function (StabilityStudyTimePoint $tp): array {
            $results = $tp->results;
            $total   = $results->count();
            $passed  = $results->where('is_pass', true)->count();
            $failed  = $results->where('is_pass', false)->count();

            return [
                'id'             => $tp->id,
                'time_point'     => $tp->time_point,
                'status'         => $tp->status,
                'scheduled_date' => $tp->scheduled_date,
                'actual_date'    => $tp->actual_date,
                'total_results'  => $total,
                'passed'         => $passed,
                'failed'         => $failed,
                'pending'        => $total - $passed - $failed,
                'results'        => $results->toArray(),
            ];
        });

        $allResults    = $study->timePoints->flatMap(fn ($tp) => $tp->results);
        $totalResults  = $allResults->count();
        $passedResults = $allResults->where('is_pass', true)->count();

        return [
            'study'          => $study->only(['id', 'uuid', 'study_number', 'study_type', 'status', 'start_date', 'planned_end_date', 'storage_condition']),
            'product'        => $study->product?->only(['id', 'name', 'sku']),
            'batch'          => $study->inventoryBatch?->only(['id', 'batch_number']),
            'time_points'    => $timePointSummaries,
            'overall'        => [
                'total_time_points' => $study->timePoints->count(),
                'total_results'     => $totalResults,
                'passed'            => $passedResults,
                'failed'            => $totalResults - $passedResults,
                'pass_rate'         => $totalResults > 0
                    ? round(($passedResults / $totalResults) * 100, 2)
                    : null,
            ],
        ];
    }
}
