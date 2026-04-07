<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\WbsElement;
use App\Services\Projects\WbsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WbsController extends Controller
{
    public function __construct(
        private WbsService $wbsService,
    ) {}

    /**
     * Return the full nested WBS hierarchy for a project.
     */
    public function hierarchy(int $projectId): JsonResponse
    {
        $tree = $this->wbsService->getHierarchy($projectId);

        return $this->success($tree->values());
    }

    /**
     * Create a new WBS element within a project.
     */
    public function createElement(Request $request, int $projectId): JsonResponse
    {
        $validated = $request->validate([
            'parent_id'               => ['nullable', 'integer', 'exists:wbs_elements,id'],
            'wbs_code'                => ['nullable', 'string', 'max:30'],
            'name'                    => ['required', 'string', 'max:200'],
            'description'             => ['nullable', 'string'],
            'status'                  => ['nullable', 'string', 'in:created,released,technically_complete,closed'],
            'planned_cost'            => ['nullable', 'numeric', 'min:0'],
            'planned_revenue'         => ['nullable', 'numeric', 'min:0'],
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sort_order'              => ['nullable', 'integer'],
            'planned_start_date'      => ['nullable', 'date'],
            'planned_end_date'        => ['nullable', 'date', 'after_or_equal:planned_start_date'],
        ]);

        $validated['project_id'] = $projectId;

        $element = $this->wbsService->createElement($validated);

        return $this->created($element);
    }

    /**
     * Update an existing WBS element.
     */
    public function updateElement(Request $request, int $projectId, WbsElement $wbsElement): JsonResponse
    {
        $validated = $request->validate([
            'wbs_code'                => ['nullable', 'string', 'max:30'],
            'name'                    => ['nullable', 'string', 'max:200'],
            'description'             => ['nullable', 'string'],
            'status'                  => ['nullable', 'string', 'in:created,released,technically_complete,closed'],
            'planned_cost'            => ['nullable', 'numeric', 'min:0'],
            'planned_revenue'         => ['nullable', 'numeric', 'min:0'],
            'responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sort_order'              => ['nullable', 'integer'],
            'planned_start_date'      => ['nullable', 'date'],
            'planned_end_date'        => ['nullable', 'date'],
            'progress_percent'        => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $element = $this->wbsService->updateElement($wbsElement, $validated);

        return $this->success($element);
    }

    /**
     * Trigger a cost rollup from child WBS elements to parents.
     */
    public function rollupCosts(int $projectId): JsonResponse
    {
        $this->wbsService->rollupCosts($projectId);

        return $this->success(null, 'Cost rollup completed successfully.');
    }
}
