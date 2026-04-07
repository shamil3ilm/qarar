<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\ProbationPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ProbationService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = ProbationPeriod::query()
            ->with(['employee', 'reviewer'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['employee_id']), fn($q) => $q->where('employee_id', $filters['employee_id']))
            ->orderBy('start_date', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): ProbationPeriod
    {
        return ProbationPeriod::create([
            'employee_id'  => $data['employee_id'],
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date'],
            'status'       => ProbationPeriod::STATUS_ACTIVE,
            'review_date'  => $data['review_date'] ?? null,
        ]);
    }

    public function update(ProbationPeriod $period, array $data): ProbationPeriod
    {
        if ($period->status === ProbationPeriod::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Completed probation periods cannot be updated.');
        }

        $period->update(array_intersect_key($data, array_flip([
            'start_date',
            'end_date',
            'review_date',
        ])));

        return $period->fresh();
    }

    public function extend(ProbationPeriod $period, string $newEndDate, ?string $reason = null): ProbationPeriod
    {
        if ($period->status !== ProbationPeriod::STATUS_ACTIVE && $period->status !== ProbationPeriod::STATUS_EXTENDED) {
            throw new InvalidArgumentException('Only active or extended probation periods can be extended.');
        }

        $period->extend($newEndDate);

        if ($reason !== null) {
            $period->review_notes = $reason;
            $period->save();
        }

        return $period->fresh();
    }

    public function complete(ProbationPeriod $period, string $outcome, int $reviewerId, string $notes): ProbationPeriod
    {
        $validOutcomes = [
            ProbationPeriod::OUTCOME_CONFIRMED,
            ProbationPeriod::OUTCOME_EXTENDED,
            ProbationPeriod::OUTCOME_TERMINATED,
        ];

        if (! in_array($outcome, $validOutcomes, true)) {
            throw new InvalidArgumentException("Invalid outcome: {$outcome}. Must be one of: " . implode(', ', $validOutcomes));
        }

        if ($period->status === ProbationPeriod::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Probation period is already completed.');
        }

        $period->complete($outcome, $reviewerId, $notes);

        return $period->fresh();
    }

    public function getDueSoon(int $orgId, int $daysAhead = 30): Collection
    {
        return ProbationPeriod::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->with(['employee'])
            ->dueSoon($daysAhead)
            ->orderBy('end_date')
            ->get();
    }

    public function waive(ProbationPeriod $period): ProbationPeriod
    {
        if ($period->status === ProbationPeriod::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Completed probation periods cannot be waived.');
        }

        $period->update(['status' => ProbationPeriod::STATUS_WAIVED]);

        return $period->fresh();
    }
}
