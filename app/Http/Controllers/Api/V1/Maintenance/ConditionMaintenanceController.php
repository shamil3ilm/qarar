<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\EquipmentSparePart;
use App\Models\Maintenance\MaintenanceConditionRule;
use App\Models\Maintenance\MaintenanceMeasurement;
use App\Services\Maintenance\ConditionBasedMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConditionMaintenanceController extends Controller
{
    public function __construct(
        private ConditionBasedMaintenanceService $cbmService
    ) {}

    /**
     * Record a new condition measurement.
     */
    public function recordMeasurement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_id'      => 'required|integer',
            'measurement_point' => 'required|string|max:100',
            'measurement_value' => 'required|numeric',
            'unit_of_measure'   => 'nullable|string|max:20',
            'measured_at'       => 'nullable|date',
        ]);

        try {
            $measurement = $this->cbmService->recordMeasurement($validated);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'MEASUREMENT_ERROR', 422);
        }

        return $this->success($measurement, 'Measurement recorded.', 201);
    }

    // -------------------------------------------------------------------------
    // Condition Rules — CRUD
    // -------------------------------------------------------------------------

    /**
     * List condition rules.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = MaintenanceConditionRule::with('equipment')
            ->when($request->equipment_id, fn($q, $v) => $q->forEquipment((int) $v))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->orderBy('equipment_id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($rules);
    }

    /**
     * Create a condition rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'          => 'required|string|max:100',
            'equipment_id'       => 'required|integer',
            'measurement_point'  => 'required|string|max:100',
            'condition_operator' => 'required|in:greater_than,less_than,equals,between',
            'threshold_value'    => 'required|numeric',
            'threshold_value_to' => 'nullable|numeric',
            'unit_of_measure'    => 'nullable|string|max:20',
            'trigger_action'     => 'required|in:create_order,notify,both',
            'maintenance_type'   => 'required|in:inspection,repair,overhaul,replacement',
            'is_active'          => 'nullable|boolean',
        ]);

        $rule = MaintenanceConditionRule::create($validated);

        return $this->success($rule, 'Condition rule created.', 201);
    }

    /**
     * Show a single condition rule.
     */
    public function show(MaintenanceConditionRule $conditionRule): JsonResponse
    {
        return $this->success($conditionRule->load('equipment'));
    }

    /**
     * Update a condition rule.
     */
    public function update(Request $request, MaintenanceConditionRule $conditionRule): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'          => 'sometimes|string|max:100',
            'measurement_point'  => 'sometimes|string|max:100',
            'condition_operator' => 'sometimes|in:greater_than,less_than,equals,between',
            'threshold_value'    => 'sometimes|numeric',
            'threshold_value_to' => 'nullable|numeric',
            'unit_of_measure'    => 'nullable|string|max:20',
            'trigger_action'     => 'sometimes|in:create_order,notify,both',
            'maintenance_type'   => 'sometimes|in:inspection,repair,overhaul,replacement',
            'is_active'          => 'sometimes|boolean',
        ]);

        $conditionRule->update($validated);

        return $this->success($conditionRule->fresh(), 'Condition rule updated.');
    }

    /**
     * Delete a condition rule.
     */
    public function destroy(MaintenanceConditionRule $conditionRule): JsonResponse
    {
        $conditionRule->delete();

        return $this->success(null, 'Condition rule deleted.');
    }

    // -------------------------------------------------------------------------
    // Spare Parts
    // -------------------------------------------------------------------------

    /**
     * List spare parts for an equipment.
     */
    public function spareParts(int $equipmentId): JsonResponse
    {
        $parts = EquipmentSparePart::with('product')
            ->forEquipment($equipmentId)
            ->get();

        return $this->success($parts);
    }

    /**
     * Add or update a spare part for an equipment.
     */
    public function addSparePart(Request $request, int $equipmentId): JsonResponse
    {
        $validated = $request->validate([
            'product_id'            => 'required|exists:products,id',
            'recommended_stock_qty' => 'required|numeric|min:0',
            'is_critical'           => 'nullable|boolean',
            'lead_time_days'        => 'nullable|numeric|min:0',
        ]);

        $validated['equipment_id'] = $equipmentId;

        try {
            $part = $this->cbmService->addSparePart($validated);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'SPARE_PART_ERROR', 422);
        }

        return $this->success($part->load('product'), 'Spare part linked to equipment.', 201);
    }

    /**
     * Check availability of all spare parts for an equipment.
     */
    public function sparePartsAvailability(int $equipmentId): JsonResponse
    {
        $availability = $this->cbmService->checkSparePartsAvailability($equipmentId);

        return $this->success($availability);
    }
}
