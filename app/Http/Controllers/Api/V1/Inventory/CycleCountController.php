<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\CycleCountLine;
use App\Models\Inventory\CycleCountPlan;
use App\Models\Inventory\CycleCountSession;
use App\Services\Inventory\CycleCountService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CycleCountController extends Controller
{
    public function __construct(private readonly CycleCountService $service) {}

    public function plans(Request $request): JsonResponse
    {
        $plans = CycleCountPlan::where('organization_id', $request->user()->organization_id)
            ->with('warehouse')
            ->paginate(20);

        return $this->paginated($plans);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_name'        => 'required|string|max:255',
            'warehouse_id'     => 'required|integer|exists:warehouses,id',
            'count_frequency'  => 'required|in:A,B,C,custom',
            'products_per_day' => 'nullable|integer|min:1',
            'scheduled_date'   => 'nullable|date',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $plan = CycleCountPlan::create($data);

        return $this->created($plan);
    }

    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'      => 'required|integer|exists:cycle_count_plans,id',
            'session_date' => 'required|date',
        ]);

        $plan    = CycleCountPlan::where('organization_id', $request->user()->organization_id)->findOrFail($data['plan_id']);
        $session = $this->service->createSession($plan, $request->user()->id, Carbon::parse($data['session_date']));

        return $this->created($session, 'Cycle count session created with ' . $session->lines->count() . ' lines');
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = CycleCountSession::where('organization_id', $request->user()->organization_id)
            ->with(['lines.product', 'lines.warehouseLocation'])
            ->findOrFail($id);

        return $this->success($session);
    }

    public function recordCount(Request $request, int $sessionId, int $lineId): JsonResponse
    {
        $data    = $request->validate(['counted_quantity' => 'required|numeric|min:0']);
        $session = CycleCountSession::where('organization_id', $request->user()->organization_id)->findOrFail($sessionId);
        $line    = CycleCountLine::where('cycle_count_session_id', $session->id)->findOrFail($lineId);

        $this->service->recordCount($line, (float) $data['counted_quantity']);

        return $this->success($line->fresh(), 'Count recorded');
    }

    public function postAdjustments(Request $request, int $id): JsonResponse
    {
        $session = CycleCountSession::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $variances = $this->service->calculateVariances($session);

        $session->update(['status' => 'posted', 'completed_at' => now()]);

        return $this->success([
            'variances' => $variances,
            'total_lines' => count($variances),
        ], 'Adjustments posted');
    }

    public function abcAnalysis(Request $request, int $warehouseId): JsonResponse
    {
        $result = $this->service->getAbcAnalysis($warehouseId);
        return $this->success($result);
    }
}
