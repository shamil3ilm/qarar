<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Services\Projects\ProjectSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProjectSettlementController extends Controller
{
    public function __construct(
        private ProjectSettlementService $settlementService,
    ) {}

    /**
     * Define a settlement rule for a project or WBS element.
     */
    public function defineRule(Request $request, int $projectId): JsonResponse
    {
        $validated = $request->validate([
            'wbs_element_id'        => ['nullable', 'integer', 'exists:wbs_elements,id'],
            'receiver_type'         => ['required', 'string', 'in:cost_center,gl_account,internal_order,profit_center'],
            'receiver_id'           => ['required', 'integer'],
            'settlement_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $validated['project_id'] = $projectId;

        $rule = $this->settlementService->defineRule($validated);

        return $this->created($rule);
    }

    /**
     * Execute settlement — distribute project costs to receivers and post journal entries.
     */
    public function settle(Request $request, int $projectId): JsonResponse
    {
        $validated = $request->validate([
            'settlement_date' => ['required', 'date'],
        ]);

        try {
            $result = $this->settlementService->settle($projectId, $validated['settlement_date']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($result, 'Project settlement completed successfully.');
    }
}
