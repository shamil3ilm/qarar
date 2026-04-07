<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\KeyPosition;
use App\Models\HR\SuccessionCandidate;
use App\Models\HR\SuccessionPoolActivity;
use Illuminate\Support\Facades\DB;

class SuccessionPlanningService
{
    /**
     * Nominate an employee as a succession candidate for a key position.
     */
    public function nominateCandidate(KeyPosition $position, Employee $employee, array $data): SuccessionCandidate
    {
        if ($position->organization_id !== $employee->organization_id) {
            throw new \InvalidArgumentException('Position and employee must belong to the same organization.');
        }

        $existing = SuccessionCandidate::where('key_position_id', $position->id)
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Employee is already nominated for this position.');
        }

        return SuccessionCandidate::create([
            'key_position_id' => $position->id,
            'employee_id' => $employee->id,
            'readiness' => $data['readiness'] ?? SuccessionCandidate::READINESS_THREE_FIVE_YEARS,
            'performance_rating' => $data['performance_rating'] ?? null,
            'potential_rating' => $data['potential_rating'] ?? null,
            'nominated_by' => auth()->id(),
            'nomination_date' => $data['nomination_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update the readiness level of a succession candidate.
     */
    public function updateReadiness(SuccessionCandidate $candidate, string $readiness, array $data = []): SuccessionCandidate
    {
        $validReadiness = [
            SuccessionCandidate::READINESS_READY_NOW,
            SuccessionCandidate::READINESS_ONE_TWO_YEARS,
            SuccessionCandidate::READINESS_THREE_FIVE_YEARS,
        ];

        if (!in_array($readiness, $validReadiness, true)) {
            throw new \InvalidArgumentException("Invalid readiness value: {$readiness}");
        }

        $candidate->update([
            'readiness' => $readiness,
            'performance_rating' => $data['performance_rating'] ?? $candidate->performance_rating,
            'potential_rating' => $data['potential_rating'] ?? $candidate->potential_rating,
            'notes' => $data['notes'] ?? $candidate->notes,
            'last_reviewed_at' => now()->toDateString(),
        ]);

        return $candidate->fresh(['employee', 'keyPosition']);
    }

    /**
     * Deactivate a succession candidate.
     */
    public function deactivateCandidate(SuccessionCandidate $candidate, ?string $reason = null): SuccessionCandidate
    {
        $candidate->update([
            'is_active' => false,
            'notes' => $reason ? ($candidate->notes . "\nDeactivated: {$reason}") : $candidate->notes,
        ]);

        return $candidate->fresh();
    }

    /**
     * Add a development activity for a succession candidate.
     */
    public function addActivity(SuccessionCandidate $candidate, array $data): SuccessionPoolActivity
    {
        return SuccessionPoolActivity::create([
            'candidate_id' => $candidate->id,
            'employee_id' => $candidate->employee_id,
            'activity_type' => $data['activity_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'status' => SuccessionPoolActivity::STATUS_PLANNED,
            'assigned_by' => auth()->id(),
        ]);
    }

    /**
     * Update the status of a development activity.
     */
    public function updateActivityStatus(SuccessionPoolActivity $activity, string $status, array $data = []): SuccessionPoolActivity
    {
        $updates = ['status' => $status];

        if ($status === SuccessionPoolActivity::STATUS_COMPLETED) {
            $updates['completed_date'] = $data['completed_date'] ?? now()->toDateString();
            $updates['outcome'] = $data['outcome'] ?? null;
        }

        $activity->update($updates);

        return $activity->fresh();
    }

    /**
     * Get the succession plan summary for an organization.
     */
    public function getSuccessionSummary(int $organizationId): array
    {
        $positions = KeyPosition::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->withCount([
                'activeCandidates',
                'activeCandidates as ready_now_count' => fn($q) => $q->where('readiness', SuccessionCandidate::READINESS_READY_NOW),
            ])
            ->get();

        return [
            'total_key_positions' => $positions->count(),
            'positions_with_successors' => $positions->where('active_candidates_count', '>', 0)->count(),
            'positions_ready_now_covered' => $positions->where('ready_now_count', '>', 0)->count(),
            'critical_gaps' => $positions->where('criticality', KeyPosition::CRITICALITY_CRITICAL)
                ->where('active_candidates_count', 0)->count(),
        ];
    }
}
