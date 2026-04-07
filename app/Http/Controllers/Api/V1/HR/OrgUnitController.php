<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\OrgUnit;
use App\Services\HR\OrgUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgUnitController extends Controller
{
    public function __construct(
        private readonly OrgUnitService $orgUnitService
    ) {}

    /**
     * List org units with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $units = $this->orgUnitService->list($request->only([
            'parent_id',
            'org_unit_type',
            'active',
            'search',
            'per_page',
        ]));

        return $this->paginated($units);
    }

    /**
     * Create a new org unit.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'org_unit_code'       => [
                'required',
                'string',
                'max:20',
                Rule::unique('org_units', 'org_unit_code')->where('organization_id', $orgId),
            ],
            'name'                => 'required|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'parent_id'           => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $orgId)],
            'org_unit_type'       => ['nullable', Rule::in([
                OrgUnit::TYPE_COMPANY,
                OrgUnit::TYPE_DIVISION,
                OrgUnit::TYPE_DEPARTMENT,
                OrgUnit::TYPE_TEAM,
                OrgUnit::TYPE_COST_CENTER_UNIT,
            ])],
            'cost_center_id'      => 'nullable|exists:cost_centers,id',
            'manager_position_id' => ['nullable', Rule::exists('positions', 'id')->where('organization_id', $orgId)],
            'head_count_plan'     => 'nullable|integer|min:0',
            'valid_from'          => 'required|date',
            'valid_to'            => 'nullable|date|after_or_equal:valid_from',
            'is_active'           => 'nullable|boolean',
        ]);

        $unit = $this->orgUnitService->create($validated);

        return $this->created($unit->load('parent'), 'Org unit created successfully.');
    }

    /**
     * Show a specific org unit.
     */
    public function show(OrgUnit $orgUnit): JsonResponse
    {
        return $this->success($orgUnit->load([
            'parent',
            'children',
            'managerPosition',
            'costCenter',
        ]));
    }

    /**
     * Update an org unit.
     */
    public function update(Request $request, OrgUnit $orgUnit): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'org_unit_code'       => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('org_units', 'org_unit_code')->where('organization_id', $orgId)->ignore($orgUnit->id),
            ],
            'name'                => 'sometimes|string|max:255',
            'short_name'          => 'nullable|string|max:50',
            'parent_id'           => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $orgId)],
            'org_unit_type'       => ['nullable', Rule::in([
                OrgUnit::TYPE_COMPANY,
                OrgUnit::TYPE_DIVISION,
                OrgUnit::TYPE_DEPARTMENT,
                OrgUnit::TYPE_TEAM,
                OrgUnit::TYPE_COST_CENTER_UNIT,
            ])],
            'cost_center_id'      => 'nullable|exists:cost_centers,id',
            'manager_position_id' => ['nullable', Rule::exists('positions', 'id')->where('organization_id', $orgId)],
            'head_count_plan'     => 'nullable|integer|min:0',
            'valid_from'          => 'sometimes|date',
            'valid_to'            => 'nullable|date',
            'is_active'           => 'nullable|boolean',
        ]);

        $unit = $this->orgUnitService->update($orgUnit, $validated);

        return $this->success($unit, 'Org unit updated successfully.');
    }

    /**
     * Delete an org unit.
     */
    public function destroy(OrgUnit $orgUnit): JsonResponse
    {
        try {
            $this->orgUnitService->delete($orgUnit);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'ORG_UNIT_DELETE_FAILED', 422);
        }

        return $this->success(null, 'Org unit deleted successfully.');
    }

    /**
     * Return the full nested org unit hierarchy.
     */
    public function hierarchy(Request $request): JsonResponse
    {
        $rootId = $request->integer('root_id') ?: null;

        $tree = $this->orgUnitService->hierarchy($rootId);

        return $this->success($tree);
    }

    /**
     * Return headcount (planned vs actual) for an org unit.
     */
    public function headcount(OrgUnit $orgUnit): JsonResponse
    {
        $headcount = $this->orgUnitService->getHeadcount($orgUnit);

        return $this->success($headcount);
    }
}
