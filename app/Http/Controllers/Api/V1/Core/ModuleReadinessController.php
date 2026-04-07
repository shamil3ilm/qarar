<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\ModuleReadinessResult;
use App\Services\Core\ModuleReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleReadinessController extends Controller
{
    public function __construct(
        protected ModuleReadinessService $readinessService
    ) {}

    /**
     * List all registered readiness checks for a module.
     * GET /module-readiness/modules/{module}/checks
     */
    public function listChecks(Request $request, string $module): JsonResponse
    {
        $checks = $this->readinessService->getChecksForModule($module);

        return $this->success($checks, "Readiness checks for module '{$module}' retrieved successfully.");
    }

    /**
     * Run all readiness checks for a module and persist the result.
     * POST /module-readiness/modules/{module}/run-checks
     */
    public function runChecks(Request $request, string $module): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $userId = $request->user()->id;

        $result = $this->readinessService->runChecks($orgId, $module, $userId);

        return $this->success($result, "Readiness checks for module '{$module}' completed.");
    }

    /**
     * Get the most recent readiness result for a module.
     * GET /module-readiness/modules/{module}/readiness-result
     */
    public function getLastResult(Request $request, string $module): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $result = $this->readinessService->getLastResult($orgId, $module);

        if ($result === null) {
            return $this->success(null, "No readiness checks have been run for module '{$module}' yet.");
        }

        return $this->success($result, 'Last readiness result retrieved successfully.');
    }

    /**
     * Paginated history of readiness results for a module.
     * GET /module-readiness/modules/{module}/readiness-results
     */
    public function listResults(Request $request, string $module): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $results = ModuleReadinessResult::where('organization_id', $orgId)
            ->where('module', $module)
            ->latest('run_at')
            ->paginate((int) $request->get('per_page', 15));

        return $this->paginated($results, null, 'Readiness results retrieved successfully.');
    }
}
