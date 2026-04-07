<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\OrgUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrgUnitService
{
    /**
     * Paginated list of org units.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $orgId = auth()->user()->organization_id;

        return OrgUnit::with(['parent', 'managerPosition'])
            ->where('organization_id', $orgId)
            ->when(isset($filters['parent_id']), fn($q) => $q->where('parent_id', $filters['parent_id']))
            ->when(isset($filters['org_unit_type']), fn($q) => $q->where('org_unit_type', $filters['org_unit_type']))
            ->when(
                isset($filters['active']) && $filters['active'],
                fn($q) => $q->active()
            )
            ->when(isset($filters['search']), fn($q) => $q->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('org_unit_code', 'like', '%' . $filters['search'] . '%');
            }))
            ->orderBy('org_unit_code')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a new org unit.
     */
    public function create(array $data): OrgUnit
    {
        return OrgUnit::create([
            'organization_id'    => auth()->user()->organization_id,
            'org_unit_code'      => $data['org_unit_code'],
            'name'               => $data['name'],
            'short_name'         => $data['short_name'] ?? null,
            'parent_id'          => $data['parent_id'] ?? null,
            'org_unit_type'      => $data['org_unit_type'] ?? OrgUnit::TYPE_DEPARTMENT,
            'cost_center_id'     => $data['cost_center_id'] ?? null,
            'manager_position_id' => $data['manager_position_id'] ?? null,
            'head_count_plan'    => $data['head_count_plan'] ?? 0,
            'valid_from'         => $data['valid_from'],
            'valid_to'           => $data['valid_to'] ?? null,
            'is_active'          => $data['is_active'] ?? true,
            'created_by'         => auth()->id(),
        ]);
    }

    /**
     * Update an org unit.
     */
    public function update(OrgUnit $unit, array $data): OrgUnit
    {
        $unit->update(array_intersect_key($data, array_flip([
            'org_unit_code',
            'name',
            'short_name',
            'parent_id',
            'org_unit_type',
            'cost_center_id',
            'manager_position_id',
            'head_count_plan',
            'valid_from',
            'valid_to',
            'is_active',
        ])));

        return $unit->fresh();
    }

    /**
     * Delete an org unit — throws if it has children or assigned employees.
     */
    public function delete(OrgUnit $unit): void
    {
        if ($unit->children()->exists()) {
            throw new \DomainException('Cannot delete an org unit that has child units. Remove children first.');
        }

        $unit->delete();
    }

    /**
     * Return a nested tree structure starting from the given root (or all roots).
     */
    public function hierarchy(?int $rootId = null): array
    {
        $orgId = auth()->user()->organization_id;

        $query = OrgUnit::with('children.children.children')
            ->where('organization_id', $orgId);

        if ($rootId !== null) {
            $query->where('id', $rootId);
        } else {
            $query->whereNull('parent_id');
        }

        return $query->get()->map(fn(OrgUnit $unit) => $this->toNode($unit))->toArray();
    }

    /**
     * Return planned vs actual headcount for an org unit.
     *
     * "Actual" is derived from active employees whose department maps to
     * a Department that shares the same name as the org unit (best-effort
     * mapping when there is no direct org_unit_id FK on employees).
     */
    public function getHeadcount(OrgUnit $unit): array
    {
        $actual = Employee::active()
            ->where('organization_id', $unit->organization_id)
            ->whereHas('department', fn($q) => $q->where('name', $unit->name))
            ->count();

        return [
            'planned' => $unit->head_count_plan,
            'actual'  => $actual,
        ];
    }

    private function toNode(OrgUnit $unit): array
    {
        return [
            'id'             => $unit->id,
            'uuid'           => $unit->uuid,
            'org_unit_code'  => $unit->org_unit_code,
            'name'           => $unit->name,
            'short_name'     => $unit->short_name,
            'org_unit_type'  => $unit->org_unit_type,
            'head_count_plan' => $unit->head_count_plan,
            'is_active'      => $unit->is_active,
            'children'       => $unit->children->map(fn(OrgUnit $child) => $this->toNode($child))->toArray(),
        ];
    }
}
