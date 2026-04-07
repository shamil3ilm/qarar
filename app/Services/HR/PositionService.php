<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PositionService
{
    /**
     * Paginate positions with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return Position::with(['department', 'designation', 'payGrade'])
            ->when($filters['department_id'] ?? null, fn($q, $v) => $q->where('department_id', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['is_key_position'] ?? null, fn($q, $v) => $q->where('is_key_position', (bool) $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($inner) use ($v) {
                    $inner->where('position_title', 'like', "%{$v}%")
                          ->orWhere('position_code', 'like', "%{$v}%");
                });
            })
            ->orderBy('position_code')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Return the full nested position hierarchy for an organization.
     */
    public function getHierarchy(int $orgId): Collection
    {
        $positions = Position::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('status', Position::STATUS_ACTIVE)
            ->with(['department', 'designation', 'payGrade'])
            ->orderBy('position_code')
            ->get();

        return $this->buildTree($positions, null);
    }

    /**
     * Create a new position.
     */
    public function store(array $data): Position
    {
        $this->validateUniqueCode($data['position_code'], $data['organization_id'] ?? auth()->user()?->organization_id);

        return Position::create([
            'organization_id'         => $data['organization_id'] ?? auth()->user()?->organization_id,
            'position_code'           => strtoupper($data['position_code']),
            'position_title'          => $data['position_title'],
            'department_id'           => $data['department_id'] ?? null,
            'designation_id'          => $data['designation_id'] ?? null,
            'pay_grade_id'            => $data['pay_grade_id'] ?? null,
            'reports_to_position_id'  => $data['reports_to_position_id'] ?? null,
            'headcount_authorized'    => $data['headcount_authorized'] ?? 1,
            'is_key_position'         => $data['is_key_position'] ?? false,
            'status'                  => Position::STATUS_ACTIVE,
        ]);
    }

    /**
     * Update an existing position.
     */
    public function update(Position $position, array $data): Position
    {
        if (
            isset($data['position_code'])
            && $data['position_code'] !== $position->position_code
        ) {
            $this->validateUniqueCode($data['position_code'], $position->organization_id, $position->id);
        }

        $position->update(array_filter([
            'position_title'         => $data['position_title'] ?? $position->position_title,
            'position_code'          => isset($data['position_code']) ? strtoupper($data['position_code']) : $position->position_code,
            'department_id'          => $data['department_id'] ?? $position->department_id,
            'designation_id'         => $data['designation_id'] ?? $position->designation_id,
            'pay_grade_id'           => $data['pay_grade_id'] ?? $position->pay_grade_id,
            'reports_to_position_id' => array_key_exists('reports_to_position_id', $data) ? $data['reports_to_position_id'] : $position->reports_to_position_id,
            'headcount_authorized'   => $data['headcount_authorized'] ?? $position->headcount_authorized,
            'is_key_position'        => $data['is_key_position'] ?? $position->is_key_position,
            'status'                 => $data['status'] ?? $position->status,
        ], fn($v) => $v !== null));

        return $position->fresh();
    }

    /**
     * Assign an employee to a position and increment headcount_filled.
     */
    public function assignEmployee(Position $position, int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);

        if (!$position->isActive()) {
            throw new \InvalidArgumentException("Cannot assign employee to a non-active position.");
        }

        if (!$position->hasVacancy()) {
            throw new \InvalidArgumentException(
                "Position '{$position->position_code}' has no vacancy (authorized: {$position->headcount_authorized}, filled: {$position->headcount_filled})."
            );
        }

        DB::transaction(function () use ($position, $employee): void {
            $employee->update(['position_id' => $position->id]);
            $position->increment('headcount_filled');
        });
    }

    /**
     * Remove an employee from a position and decrement headcount_filled.
     */
    public function vacatePosition(Position $position, int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);

        DB::transaction(function () use ($position, $employee): void {
            $employee->update(['position_id' => null]);
            $position->decrement('headcount_filled');
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildTree(Collection $positions, ?int $parentId): Collection
    {
        return $positions
            ->filter(fn($p) => $p->reports_to_position_id === $parentId)
            ->values()
            ->map(function ($position) use ($positions) {
                $position->children = $this->buildTree($positions, $position->id);
                return $position;
            });
    }

    private function validateUniqueCode(string $code, ?int $orgId, ?int $excludeId = null): void
    {
        $exists = Position::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('position_code', strtoupper($code))
            ->when($excludeId, fn($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException("Position code '{$code}' already exists in this organization.");
        }
    }
}
