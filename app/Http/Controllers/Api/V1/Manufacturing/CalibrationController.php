<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CalibrationCertificate;
use App\Models\Manufacturing\CalibrationEquipment;
use App\Models\Manufacturing\CalibrationOrder;
use App\Models\Manufacturing\CalibrationPlan;
use App\Services\Manufacturing\CalibrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalibrationController extends Controller
{
    public function __construct(
        private CalibrationService $calibrationService,
    ) {}

    // -------------------------------------------------------------------------
    // Equipment
    // -------------------------------------------------------------------------

    public function equipment(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = CalibrationEquipment::where('organization_id', $orgId)
            ->with('responsiblePerson')
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->input('category')))
            ->when($request->boolean('active_only'), fn($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', $search)
                        ->orWhere('equipment_code', 'like', $search)
                        ->orWhere('serial_number', 'like', $search);
                });
            });

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderBy('name')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_code'        => 'required|string|max:30',
            'name'                  => 'required|string|max:255',
            'manufacturer'          => 'nullable|string|max:100',
            'model_number'          => 'nullable|string|max:50',
            'serial_number'         => 'nullable|string|max:50',
            'category'              => 'nullable|string|max:50',
            'location'              => 'nullable|string|max:100',
            'responsible_person_id' => 'nullable|integer|exists:users,id',
            'purchase_date'         => 'nullable|date',
            'is_active'             => 'boolean',
        ]);

        $equipment = CalibrationEquipment::create([
            'organization_id' => $this->organizationId($request),
            ...$validated,
        ]);

        return $this->created($equipment);
    }

    public function showEquipment(Request $request, int $id): JsonResponse
    {
        $equipment = CalibrationEquipment::where('organization_id', $this->organizationId($request))
            ->with(['responsiblePerson', 'calibrationPlans', 'calibrationOrders' => function ($q) {
                $q->orderByDesc('scheduled_date')->limit(5);
            }])
            ->find($id);

        if ($equipment === null) {
            return $this->notFound('Calibration equipment not found.');
        }

        return $this->success($equipment);
    }

    public function updateEquipment(Request $request, int $id): JsonResponse
    {
        $equipment = CalibrationEquipment::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($equipment === null) {
            return $this->notFound('Calibration equipment not found.');
        }

        $validated = $request->validate([
            'equipment_code'        => 'sometimes|string|max:30',
            'name'                  => 'sometimes|string|max:255',
            'manufacturer'          => 'nullable|string|max:100',
            'model_number'          => 'nullable|string|max:50',
            'serial_number'         => 'nullable|string|max:50',
            'category'              => 'nullable|string|max:50',
            'location'              => 'nullable|string|max:100',
            'responsible_person_id' => 'nullable|integer|exists:users,id',
            'purchase_date'         => 'nullable|date',
            'is_active'             => 'boolean',
        ]);

        $equipment->update($validated);

        return $this->success($equipment->fresh());
    }

    // -------------------------------------------------------------------------
    // Plans
    // -------------------------------------------------------------------------

    public function plans(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = CalibrationPlan::where('organization_id', $orgId)
            ->with('equipment')
            ->when($request->filled('equipment_id'), fn($q) => $q->where('calibration_equipment_id', $request->input('equipment_id')))
            ->when($request->boolean('active_only'), fn($q) => $q->where('is_active', true));

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderBy('plan_code')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calibration_equipment_id'  => 'required|integer|exists:calibration_equipment,id',
            'plan_code'                 => 'required|string|max:30',
            'calibration_interval_days' => 'required|integer|min:1',
            'tolerance_low'             => 'nullable|numeric',
            'tolerance_high'            => 'nullable|numeric',
            'measurement_unit'          => 'nullable|string|max:20',
            'calibration_procedure'     => 'nullable|string',
            'external_lab'              => 'nullable|string|max:100',
            'is_active'                 => 'boolean',
        ]);

        $plan = CalibrationPlan::create([
            'organization_id' => $this->organizationId($request),
            ...$validated,
        ]);

        return $this->created($plan->load('equipment'));
    }

    public function showPlan(Request $request, int $id): JsonResponse
    {
        $plan = CalibrationPlan::where('organization_id', $this->organizationId($request))
            ->with(['equipment', 'calibrationOrders' => function ($q) {
                $q->orderByDesc('scheduled_date')->limit(10);
            }])
            ->find($id);

        if ($plan === null) {
            return $this->notFound('Calibration plan not found.');
        }

        return $this->success($plan);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    public function orders(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = CalibrationOrder::where('organization_id', $orgId)
            ->with(['equipment', 'plan', 'calibratedBy'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('equipment_id'), fn($q) => $q->where('calibration_equipment_id', $request->input('equipment_id')))
            ->when($request->filled('result'), fn($q) => $q->where('result', $request->input('result')));

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderByDesc('scheduled_date')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calibration_equipment_id' => 'required|integer|exists:calibration_equipment,id',
            'calibration_plan_id'      => 'nullable|integer|exists:calibration_plans,id',
            'scheduled_date'           => 'required|date',
            'external_lab'             => 'nullable|string|max:100',
            'notes'                    => 'nullable|string',
        ]);

        $orgId  = $this->organizationId($request);
        $order  = CalibrationOrder::create([
            'organization_id' => $orgId,
            'order_number'    => CalibrationOrder::generateOrderNumber($orgId),
            'status'          => CalibrationOrder::STATUS_PLANNED,
            ...$validated,
        ]);

        return $this->created($order->load(['equipment', 'plan']));
    }

    public function showOrder(Request $request, int $id): JsonResponse
    {
        $order = CalibrationOrder::where('organization_id', $this->organizationId($request))
            ->with(['equipment', 'plan', 'calibratedBy', 'certificates'])
            ->find($id);

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        return $this->success($order);
    }

    public function completeOrder(Request $request, int $id): JsonResponse
    {
        $order = CalibrationOrder::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        if (!in_array($order->status, [CalibrationOrder::STATUS_PLANNED, CalibrationOrder::STATUS_IN_PROGRESS], true)) {
            return $this->error('Only planned or in-progress orders can be completed.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'result'              => 'required|in:pass,fail,conditional',
            'actual_measurement'  => 'nullable|numeric',
            'calibrated_by'       => 'nullable|integer|exists:users,id',
            'notes'               => 'nullable|string',
            'certificate'         => 'nullable|array',
            'certificate.certificate_number' => 'required_with:certificate|string|max:50',
            'certificate.issued_date'        => 'nullable|date',
            'certificate.valid_until'        => 'nullable|date',
            'certificate.issued_by'          => 'nullable|string|max:100',
            'certificate.accreditation_body' => 'nullable|string|max:100',
            'certificate.certificate_data'   => 'nullable|array',
        ]);

        $this->calibrationService->completeCalibration($order, $validated);

        return $this->success($order->fresh(['equipment', 'plan', 'certificates']));
    }

    public function certificates(Request $request, int $orderId): JsonResponse
    {
        $order = CalibrationOrder::where('organization_id', $this->organizationId($request))
            ->find($orderId);

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        $certs = CalibrationCertificate::where('calibration_order_id', $orderId)
            ->orderByDesc('issued_date')
            ->get();

        return $this->success($certs);
    }

    // -------------------------------------------------------------------------
    // Alerts & Bulk Actions
    // -------------------------------------------------------------------------

    public function overdue(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $equipment = $this->calibrationService->getOverdueEquipment($orgId);

        return $this->success($equipment);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $days  = (int) $request->input('days', 30);
        $orders = $this->calibrationService->getUpcomingCalibrations($orgId, $days);

        return $this->success($orders);
    }

    public function generateOrders(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $count = $this->calibrationService->generateCalibrationOrders($orgId);

        return $this->success(['generated_count' => $count]);
    }
}
