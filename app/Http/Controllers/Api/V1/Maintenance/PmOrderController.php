<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PmCounter;
use App\Models\Maintenance\PmMaintenancePlan;
use App\Models\Maintenance\PmOrder;
use App\Services\Maintenance\CounterBasedPmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmOrderController extends Controller
{
    public function __construct(private readonly CounterBasedPmService $service) {}

    public function counters(Request $request): JsonResponse
    {
        $counters = PmCounter::where('organization_id', $request->user()->organization_id)
            ->with(['equipment', 'functionalLocation'])
            ->paginate(20);

        return $this->paginated($counters);
    }

    public function storeCounter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counter_name'   => 'required|string|max:255',
            'equipment_id'   => 'nullable|integer',
            'floc_id'        => 'nullable|integer',
            'uom'            => 'required|string|max:20',
            'overflow_value' => 'nullable|numeric',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['uuid']            = (string) \Illuminate\Support\Str::uuid();

        $counter = PmCounter::create($data);

        return $this->created($counter);
    }

    public function recordReading(Request $request, int $counterId): JsonResponse
    {
        $data = $request->validate([
            'reading_value' => 'required|numeric|min:0',
            'reading_date'  => 'required|date',
        ]);

        $counter  = PmCounter::where('organization_id', $request->user()->organization_id)->findOrFail($counterId);
        $reading  = $this->service->recordReading(
            $counter,
            (float) $data['reading_value'],
            Carbon::parse($data['reading_date']),
            $request->user()->id
        );

        return $this->success($reading, 'Reading recorded successfully');
    }

    public function plans(Request $request): JsonResponse
    {
        $plans = PmMaintenancePlan::where('organization_id', $request->user()->organization_id)
            ->with(['functionalLocation', 'counter', 'taskList'])
            ->paginate(20);

        return $this->paginated($plans);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_number'       => 'required|string|max:50|unique:pm_maintenance_plans',
            'plan_type'         => 'required|in:time_based,counter_based,condition_based',
            'floc_id'           => 'nullable|integer',
            'counter_id'        => 'nullable|integer',
            'task_list_id'      => 'nullable|integer',
            'counter_interval'  => 'nullable|numeric|min:0',
            'threshold_warning' => 'nullable|numeric|min:0',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['uuid']            = (string) \Illuminate\Support\Str::uuid();

        $plan = PmMaintenancePlan::create($data);

        return $this->created($plan);
    }

    public function dueOrders(Request $request): JsonResponse
    {
        $due = $this->service->checkDueOrders($request->user()->organization_id);
        return $this->success($due);
    }

    public function generateOrder(Request $request, int $planId): JsonResponse
    {
        $plan  = PmMaintenancePlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $order = $this->service->generatePmOrder($plan);

        return $this->created($order, 'PM Order generated');
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = PmOrder::where('organization_id', $request->user()->organization_id)
            ->with(['maintenancePlan', 'functionalLocation'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->paginated($orders);
    }

    public function completeOrder(Request $request, int $orderId): JsonResponse
    {
        $data  = $request->validate(['actual_end' => 'nullable|date']);
        $order = PmOrder::where('organization_id', $request->user()->organization_id)->findOrFail($orderId);

        $this->service->completePmOrder($order, $data);

        return $this->success($order->fresh(), 'PM Order completed');
    }
}
