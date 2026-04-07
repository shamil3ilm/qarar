<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Services\Manufacturing\DynamicModificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicModificationController extends Controller
{
    public function __construct(
        private readonly DynamicModificationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rules = $this->service->listRules(
            $request->user()->organization_id,
            $request->only(['is_active', 'per_page']),
        );

        return $this->paginated($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rule_code'                      => 'required|string|max:20',
            'name'                           => 'required|string|max:100',
            'description'                    => 'nullable|string',
            'tighten_consecutive_fails'      => 'sometimes|integer|min:1|max:100',
            'reduce_after_consecutive_pass'  => 'sometimes|integer|min:1|max:100',
            'skip_after_reduced_pass'        => 'sometimes|integer|min:1|max:100',
            'reinstate_after_tightened_fail' => 'sometimes|integer|min:1|max:100',
            'is_active'                      => 'boolean',
        ]);

        $rule = $this->service->createRule(
            $request->user()->organization_id,
            $data,
            $request->user()->id,
        );

        return $this->created($rule);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $rule = $this->service->findRule(
            $request->user()->organization_id,
            $uuid,
        );

        return $this->success($rule);
    }

    /**
     * POST evaluate — record a pass/fail result and get stage recommendation.
     */
    public function evaluate(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'product_id'  => 'required|integer|exists:products,id',
            'supplier_id' => 'nullable|integer',
            'passed'      => 'required|boolean',
        ]);

        $rule = $this->service->findRule(
            $request->user()->organization_id,
            $uuid,
        );

        $result = $this->service->evaluateInspectionResult(
            $request->user()->organization_id,
            $rule->id,
            (int) $data['product_id'],
            isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            (bool) $data['passed'],
        );

        return $this->success($result);
    }

    /**
     * GET current stage for a product/supplier combination under a rule.
     */
    public function currentStage(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'product_id'  => 'required|integer',
            'supplier_id' => 'nullable|integer',
        ]);

        $rule = $this->service->findRule(
            $request->user()->organization_id,
            $uuid,
        );

        $stageLog = $this->service->getCurrentStage(
            $request->user()->organization_id,
            $rule->id,
            (int) $data['product_id'],
            isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
        );

        return $this->success($stageLog);
    }
}
