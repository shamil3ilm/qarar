<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\SkipLotDecision;
use App\Models\Manufacturing\SkipLotSamplingPlan;
use App\Services\Manufacturing\SkipLotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkipLotController extends Controller
{
    public function __construct(private readonly SkipLotService $service) {}

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $plans = $this->service->list($orgId, $request->query());
        return $this->success($plans, 'Skip lot sampling plans retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_code'                          => 'required|string|max:30',
            'plan_name'                          => 'required|string|max:100',
            'plan_type'                          => 'required|in:skip_lot,reduced,normal,tightened',
            'inspection_frequency'               => 'integer|min:1|max:255',
            'sample_size_percent'                => 'numeric|min:0|max:100',
            'accept_number'                      => 'integer|min:0',
            'reject_number'                      => 'integer|min:1',
            'switch_rule_reduced_to_normal'      => 'nullable|integer|min:1',
            'switch_rule_normal_to_tightened'    => 'nullable|integer|min:1',
            'switch_rule_tightened_to_rejected'  => 'nullable|integer|min:1',
            'is_active'                          => 'boolean',
        ]);

        $plan = $this->service->createPlan($request->user()->organization_id, $data);
        return $this->created($plan, 'Sampling plan created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = SkipLotSamplingPlan::where('organization_id', $request->user()->organization_id)
            ->with('decisions')
            ->findOrFail($id);
        return $this->success($plan, 'Sampling plan retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = SkipLotSamplingPlan::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'plan_code'                          => 'string|max:30',
            'plan_name'                          => 'string|max:100',
            'plan_type'                          => 'in:skip_lot,reduced,normal,tightened',
            'inspection_frequency'               => 'integer|min:1|max:255',
            'sample_size_percent'                => 'numeric|min:0|max:100',
            'accept_number'                      => 'integer|min:0',
            'reject_number'                      => 'integer|min:1',
            'switch_rule_reduced_to_normal'      => 'nullable|integer|min:1',
            'switch_rule_normal_to_tightened'    => 'nullable|integer|min:1',
            'switch_rule_tightened_to_rejected'  => 'nullable|integer|min:1',
            'is_active'                          => 'boolean',
        ]);

        $updated = $this->service->updatePlan($plan, $data);
        return $this->success($updated, 'Sampling plan updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $plan = SkipLotSamplingPlan::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $plan->delete();
        return $this->success(null, 'Sampling plan deleted.');
    }

    public function decisions(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $decisions = $this->service->getDecisions($orgId, $request->query());
        return $this->success($decisions, 'Skip lot decisions retrieved.');
    }

    public function shouldInspect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id'  => 'required|integer',
            'product_id' => 'required|integer',
            'plan_id'    => 'required|integer',
        ]);

        $orgId    = $request->user()->organization_id;
        $decision = $this->service->getOrCreateDecision(
            (int) $data['vendor_id'],
            (int) $data['product_id'],
            (int) $data['plan_id'],
            $orgId
        );

        $result = $this->service->shouldInspect($decision);

        return $this->success([
            'should_inspect' => $result,
            'current_level'  => $decision->current_level,
            'decision'       => $decision,
        ], 'Inspection check performed.');
    }

    public function recordResult(Request $request, int $id): JsonResponse
    {
        $decision = SkipLotDecision::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'accepted'            => 'required|boolean',
            'inspection_lot_id'   => 'required|integer',
        ]);

        $this->service->recordResult($decision, (bool) $data['accepted'], (int) $data['inspection_lot_id']);

        return $this->success($decision->fresh(), 'Result recorded and level evaluated.');
    }
}
