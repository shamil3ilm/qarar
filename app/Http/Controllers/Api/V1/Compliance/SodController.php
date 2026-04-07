<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\SodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SodController extends Controller
{
    public function __construct(
        private readonly SodService $service
    ) {}

    public function indexFunctions(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $functions = \App\Models\Compliance\SodFunction::where('organization_id', $organizationId)
            ->orderBy('module')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($functions);
    }

    public function storeFunction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'function_code' => ['required', 'string', 'max:50'],
            'name'          => ['required', 'string', 'max:150'],
            'module'        => ['required', 'string', 'max:50'],
            'description'   => ['nullable', 'string'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_active'     => ['boolean'],
        ]);

        $organizationId = $this->organizationId($request);

        $function = $this->service->createFunction($organizationId, $data);

        return $this->created($function, 'SoD function created');
    }

    public function indexConflicts(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $conflicts = $this->service->listConflicts($organizationId);

        return $this->success($conflicts);
    }

    public function storeConflict(Request $request): JsonResponse
    {
        $data = $request->validate([
            'function_a_id' => ['required', 'integer', 'exists:grc_sod_functions,id'],
            'function_b_id' => ['required', 'integer', 'exists:grc_sod_functions,id', 'different:function_a_id'],
            'risk_level'    => ['required', 'in:critical,high,medium,low'],
            'description'   => ['nullable', 'string'],
            'mitigation'    => ['nullable', 'string'],
            'is_active'     => ['boolean'],
        ]);

        $organizationId = $this->organizationId($request);

        $conflict = $this->service->createConflict($organizationId, $data);

        return $this->created($conflict->load(['functionA', 'functionB']), 'SoD conflict created');
    }

    public function indexViolations(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $filters = $request->only(['user_id', 'status', 'risk_level', 'per_page']);

        $paginator = $this->service->listViolations($organizationId, $filters);

        return $this->paginated($paginator);
    }

    public function runScan(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $result = $this->service->runOrganizationScan($organizationId);

        return $this->success($result, 'SoD scan completed');
    }

    public function reviewUser(Request $request, int $userId): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $result = $this->service->runUserAccessReview($organizationId, $userId);

        return $this->success($result);
    }

    public function acceptRisk(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'mitigation_description' => ['nullable', 'string'],
            'review_date'            => ['nullable', 'date'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $violation = $this->service->acceptRisk($organizationId, $uuid, $data, $userId);

        return $this->success($violation, 'Risk accepted');
    }
}
