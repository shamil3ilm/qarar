<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceServiceOrder::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['equipment'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('vendor_id'), fn($q) => $q->where('vendor_id', $request->integer('vendor_id')));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_id'         => 'nullable|integer',
            'maintenance_order_id' => 'nullable|integer',
            'vendor_id'            => 'nullable|integer',
            'service_type'         => 'required|in:repair,inspection,installation,calibration,overhaul',
            'description'          => 'required|string',
            'requested_date'       => 'required|date',
            'due_date'             => 'required|date',
            'estimated_cost'       => 'nullable|numeric|min:0',
            'sla_response_hours'   => 'nullable|string',
            'sla_resolution_hours' => 'nullable|string',
        ]);

        $so = MaintenanceServiceOrder::create([
            ...$validated,
            'organization_id'       => $request->user()->organization_id,
            'service_order_number'  => 'SO-' . strtoupper(Str::random(8)),
            'status'                => MaintenanceServiceOrder::STATUS_DRAFT,
            'sla_response_due_at'   => isset($validated['sla_response_hours'])
                ? now()->addHours((int) $validated['sla_response_hours'])
                : null,
            'sla_resolution_due_at' => isset($validated['sla_resolution_hours'])
                ? now()->addHours((int) $validated['sla_resolution_hours'])
                : null,
            'created_by'            => $request->user()->id,
        ]);

        return $this->created($so);
    }

    public function show(MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        return $this->success($serviceOrder->load('equipment', 'maintenanceOrder'));
    }

    public function update(Request $request, MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        $validated = $request->validate([
            'status'         => 'sometimes|in:draft,issued,confirmed,in_progress,completed,cancelled',
            'actual_cost'    => 'sometimes|numeric|min:0',
            'completed_date' => 'sometimes|nullable|date',
            'vendor_notes'   => 'sometimes|nullable|string',
        ]);

        if (
            isset($validated['status'])
            && $validated['status'] === MaintenanceServiceOrder::STATUS_COMPLETED
        ) {
            $validated['completed_date'] ??= now()->toDateString();
        }

        if (
            isset($validated['status'])
            && $validated['status'] === MaintenanceServiceOrder::STATUS_CONFIRMED
        ) {
            $validated['vendor_responded_at'] = now();
        }

        $serviceOrder->update($validated);

        return $this->success($serviceOrder);
    }

    public function destroy(MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        $serviceOrder->delete();

        return $this->success(null, 'Service order deleted');
    }
}
