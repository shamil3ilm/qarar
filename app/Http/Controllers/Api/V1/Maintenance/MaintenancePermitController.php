<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenancePermit;
use App\Models\Maintenance\PermitSafetyCheck;
use App\Services\Maintenance\MaintenancePermitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MaintenancePermitController extends Controller
{
    public function __construct(private readonly MaintenancePermitService $service) {}

    public function index(Request $request): JsonResponse
    {
        $permits = $this->service->list($request->user()->organization_id, $request->query());
        return $this->success($permits, 'Maintenance permits retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maintenance_order_id'  => 'nullable|integer|exists:maintenance_orders,id',
            'permit_number'         => 'required|string|max:50',
            'permit_type'           => 'required|in:hot_work,confined_space,electrical_isolation,height_work,chemical,general',
            'valid_from'            => 'nullable|date',
            'valid_until'           => 'nullable|date|after_or_equal:valid_from',
            'location'              => 'nullable|string|max:255',
            'work_description'      => 'nullable|string',
            'hazards_identified'    => 'nullable|string',
            'precautions_required'  => 'nullable|string',
            'requested_by'          => 'nullable|integer|exists:users,id',
        ]);

        $permit = $this->service->create($request->user()->organization_id, $data);
        return $this->created($permit, 'Maintenance permit created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)
            ->with(['maintenanceOrder', 'requestedByUser', 'approvedByUser', 'closedByUser', 'safetyChecks.completedByUser'])
            ->findOrFail($id);
        return $this->success($permit, 'Permit retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'permit_number'         => 'string|max:50',
            'permit_type'           => 'in:hot_work,confined_space,electrical_isolation,height_work,chemical,general',
            'valid_from'            => 'nullable|date',
            'valid_until'           => 'nullable|date',
            'location'              => 'nullable|string|max:255',
            'work_description'      => 'nullable|string',
            'hazards_identified'    => 'nullable|string',
            'precautions_required'  => 'nullable|string',
        ]);

        try {
            $updated = $this->service->update($permit, $data);
            return $this->success($updated, 'Permit updated.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->approve($permit, $request->user()->id);
            return $this->success($updated, 'Permit approved.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->activate($permit);
            return $this->success($updated, 'Permit activated.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->suspend($permit);
            return $this->success($updated, 'Permit suspended.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->close($permit, $request->user()->id);
            return $this->success($updated, 'Permit closed.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function addSafetyCheck(Request $request, int $id): JsonResponse
    {
        $permit = MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'check_description' => 'required|string|max:255',
            'is_mandatory'      => 'boolean',
            'remarks'           => 'nullable|string',
            'sort_order'        => 'integer|min:0',
        ]);

        $check = $this->service->addSafetyCheck($permit, $data);
        return $this->created($check, 'Safety check added.');
    }

    public function completeSafetyCheck(Request $request, int $id, int $checkId): JsonResponse
    {
        MaintenancePermit::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $check = PermitSafetyCheck::where('maintenance_permit_id', $id)->findOrFail($checkId);

        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        if ($request->has('remarks')) {
            $check->update(['remarks' => $request->input('remarks')]);
        }

        try {
            $updated = $this->service->completeSafetyCheck($check, $request->user()->id);
            return $this->success($updated, 'Safety check completed.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }
}
