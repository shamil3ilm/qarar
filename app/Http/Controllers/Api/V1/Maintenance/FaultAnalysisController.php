<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceFaultCode;
use App\Models\Maintenance\MaintenanceRca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaultAnalysisController extends Controller
{
    // -------------------------------------------------------------------------
    // Fault Codes
    // -------------------------------------------------------------------------

    public function indexFaultCodes(Request $request): JsonResponse
    {
        $codes = MaintenanceFaultCode::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get();

        return $this->success($codes);
    }

    public function storeFaultCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'               => 'required|string|max:20',
            'description'        => 'required|string|max:255',
            'fault_type'         => 'required|in:mechanical,electrical,hydraulic,software,operator,wear,other',
            'cause'              => 'nullable|string',
            'recommended_action' => 'nullable|string',
        ]);

        $code = MaintenanceFaultCode::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
        ]);

        return $this->created($code);
    }

    // -------------------------------------------------------------------------
    // Root Cause Analysis
    // -------------------------------------------------------------------------

    public function indexRca(Request $request): JsonResponse
    {
        $query = MaintenanceRca::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['faultCode', 'maintenanceOrder'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('equipment_id'), fn($q) => $q->where('equipment_id', $request->integer('equipment_id')));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeRca(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_order_id' => 'required|integer',
            'equipment_id'         => 'required|integer',
            'fault_code_id'        => 'nullable|integer',
            'rca_method'           => 'required|in:5_why,fishbone,fault_tree,fmea,other',
            'whys'                 => 'nullable|array',
            'root_cause'           => 'nullable|string',
            'corrective_actions'   => 'nullable|string',
            'preventive_actions'   => 'nullable|string',
            'assigned_to'          => 'nullable|integer',
            'target_date'          => 'nullable|date',
        ]);

        $rca = MaintenanceRca::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
        ]);

        return $this->created($rca->load('faultCode'));
    }

    public function updateRca(Request $request, MaintenanceRca $rca): JsonResponse
    {
        $validated = $request->validate([
            'root_cause'           => 'sometimes|string',
            'contributing_factors' => 'sometimes|string',
            'corrective_actions'   => 'sometimes|string',
            'preventive_actions'   => 'sometimes|string',
            'status'               => 'sometimes|in:open,in_progress,closed',
            'closed_date'          => 'sometimes|nullable|date',
        ]);

        if (
            isset($validated['status'])
            && $validated['status'] === MaintenanceRca::STATUS_CLOSED
        ) {
            $validated['closed_date'] ??= now()->toDateString();
        }

        $rca->update($validated);

        return $this->success($rca);
    }
}
