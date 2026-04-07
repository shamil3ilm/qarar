<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\FuelLog;
use App\Models\Maintenance\MileageLog;
use App\Models\Maintenance\Vehicle;
use App\Models\Maintenance\VehicleMaintenanceRecord;
use App\Services\Maintenance\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function __construct(
        private FleetService $fleetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = Vehicle::where('organization_id', $orgId)
            ->with(['department'])
            ->when($request->filled('vehicle_type'), fn($q) => $q->where('vehicle_type', $request->input('vehicle_type')))
            ->when($request->boolean('active_only'), fn($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(function ($q) use ($search): void {
                    $q->where('fleet_number', 'like', $search)
                        ->orWhere('license_plate', 'like', $search)
                        ->orWhere('make', 'like', $search)
                        ->orWhere('model', 'like', $search);
                });
            });

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderBy('fleet_number')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fleet_number'        => 'required|string|max:20',
            'license_plate'       => 'required|string|max:20',
            'make'                => 'required|string|max:50',
            'model'               => 'required|string|max:50',
            'year'                => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'vin'                 => 'nullable|string|max:50',
            'vehicle_type'        => 'required|in:car,van,truck,motorcycle,bus,other',
            'fuel_type'           => 'required|in:petrol,diesel,electric,hybrid,cng',
            'color'               => 'nullable|string|max:30',
            'department_id'       => 'nullable|integer|exists:departments,id',
            'current_mileage_km'  => 'integer|min:0',
            'last_service_km'     => 'nullable|integer|min:0',
            'next_service_km'     => 'nullable|integer|min:0',
            'insurance_expiry'    => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'is_active'           => 'boolean',
        ]);

        $vehicle = $this->fleetService->create($validated);

        return $this->created($vehicle->load('department'));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->with(['department', 'assignments.driver'])
            ->find($id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        return $this->success($vehicle);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'fleet_number'        => 'sometimes|string|max:20',
            'license_plate'       => 'sometimes|string|max:20',
            'make'                => 'sometimes|string|max:50',
            'model'               => 'sometimes|string|max:50',
            'year'                => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'vin'                 => 'nullable|string|max:50',
            'vehicle_type'        => 'sometimes|in:car,van,truck,motorcycle,bus,other',
            'fuel_type'           => 'sometimes|in:petrol,diesel,electric,hybrid,cng',
            'color'               => 'nullable|string|max:30',
            'department_id'       => 'nullable|integer|exists:departments,id',
            'current_mileage_km'  => 'sometimes|integer|min:0',
            'last_service_km'     => 'nullable|integer|min:0',
            'next_service_km'     => 'nullable|integer|min:0',
            'insurance_expiry'    => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'is_active'           => 'boolean',
        ]);

        $vehicle->update($validated);

        return $this->success($vehicle->fresh('department'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $vehicle->delete();

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Driver Assignment
    // -------------------------------------------------------------------------

    public function assign(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'driver_id'     => 'required|integer|exists:employees,id',
            'assigned_from' => 'nullable|date_format:Y-m-d H:i:s',
            'assigned_to'   => 'nullable|date_format:Y-m-d H:i:s|after:assigned_from',
            'purpose'       => 'nullable|string|max:100',
        ]);

        $assignment = $this->fleetService->assignDriver(
            $vehicleId,
            $validated['driver_id'],
            $validated,
        );

        return $this->created($assignment->load('driver'));
    }

    public function unassign(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $this->fleetService->unassignDriver($vehicleId);

        return $this->success(['message' => 'Driver unassigned successfully.']);
    }

    // -------------------------------------------------------------------------
    // Mileage Logs
    // -------------------------------------------------------------------------

    public function mileageLogs(Request $request, int $vehicleId): JsonResponse
    {
        Vehicle::where('organization_id', $this->organizationId($request))
            ->findOrFail($vehicleId);

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = MileageLog::where('vehicle_id', $vehicleId)
            ->with('driver')
            ->orderByDesc('log_date')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function logMileage(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'log_date'      => 'nullable|date',
            'odometer_start' => 'required|integer|min:0',
            'odometer_end'  => 'required|integer|gt:odometer_start',
            'trip_purpose'  => 'nullable|string|max:100',
            'driver_id'     => 'nullable|integer|exists:employees,id',
            'route'         => 'nullable|string|max:200',
        ]);

        $log = $this->fleetService->logMileage([
            'vehicle_id' => $vehicleId,
            ...$validated,
        ]);

        return $this->created($log);
    }

    // -------------------------------------------------------------------------
    // Fuel Logs
    // -------------------------------------------------------------------------

    public function fuelLogs(Request $request, int $vehicleId): JsonResponse
    {
        Vehicle::where('organization_id', $this->organizationId($request))
            ->findOrFail($vehicleId);

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = FuelLog::where('vehicle_id', $vehicleId)
            ->with('filledBy')
            ->orderByDesc('log_date')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function logFuel(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'log_date'             => 'nullable|date',
            'odometer_reading'     => 'required|integer|min:0',
            'fuel_quantity_liters' => 'required|numeric|min:0.001',
            'fuel_cost'            => 'required|numeric|min:0',
            'currency_code'        => 'required|string|size:3',
            'fuel_type'            => 'required|in:petrol,diesel,electric,hybrid,cng',
            'station'              => 'nullable|string|max:100',
            'filled_by'            => 'nullable|integer|exists:employees,id',
        ]);

        $log = $this->fleetService->logFuel([
            'vehicle_id' => $vehicleId,
            ...$validated,
        ]);

        return $this->created($log);
    }

    // -------------------------------------------------------------------------
    // Vehicle Maintenance
    // -------------------------------------------------------------------------

    public function maintenanceRecords(Request $request, int $vehicleId): JsonResponse
    {
        Vehicle::where('organization_id', $this->organizationId($request))
            ->findOrFail($vehicleId);

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = VehicleMaintenanceRecord::where('vehicle_id', $vehicleId)
            ->orderByDesc('service_date')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function recordMaintenance(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::where('organization_id', $this->organizationId($request))
            ->find($vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'maintenance_type'     => 'required|in:scheduled,unscheduled,repair,inspection',
            'service_date'         => 'required|date',
            'odometer_reading'     => 'nullable|integer|min:0',
            'description'          => 'required|string',
            'cost'                 => 'nullable|numeric|min:0',
            'currency_code'        => 'nullable|string|size:3',
            'service_provider'     => 'nullable|string|max:100',
            'next_service_date'    => 'nullable|date|after:service_date',
            'next_service_km'      => 'nullable|integer|min:0',
            'maintenance_order_id' => 'nullable|integer',
        ]);

        $record = $this->fleetService->recordMaintenance([
            'vehicle_id' => $vehicleId,
            ...$validated,
        ]);

        return $this->created($record);
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    public function requiringService(Request $request): JsonResponse
    {
        $orgId    = $this->organizationId($request);
        $vehicles = $this->fleetService->getVehiclesRequiringService($orgId);

        return $this->success($vehicles);
    }

    public function costSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $orgId   = $this->organizationId($request);
        $summary = $this->fleetService->getFleetCostSummary(
            $orgId,
            $validated['date_from'],
            $validated['date_to'],
        );

        return $this->success($summary);
    }
}
