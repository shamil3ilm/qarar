<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\DockDoor;
use App\Models\Inventory\TruckAppointment;
use App\Models\Inventory\YardZone;
use App\Services\Inventory\YardManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class YardManagementController extends Controller
{
    public function __construct(
        private readonly YardManagementService $yardService,
    ) {}

    // ── Yard Zones ────────────────────────────────────────────────────────────

    /**
     * GET /inventory/yard/zones
     */
    public function zones(Request $request): JsonResponse
    {
        $zones = YardZone::where('organization_id', $request->user()->organization_id)
            ->when($request->input('warehouse_id'), fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->with('warehouse')
            ->orderBy('zone_code')
            ->get();

        return $this->success($zones);
    }

    /**
     * POST /inventory/yard/zones
     */
    public function storeZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'      => 'required|integer',
            'zone_code'         => 'required|string|max:20',
            'name'              => 'required|string|max:100',
            'zone_type'         => 'nullable|in:staging,parking,inspection,dock',
            'capacity_vehicles' => 'nullable|integer|min:1',
            'is_active'         => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['is_active']       = $validated['is_active'] ?? true;

        $zone = YardZone::create($validated);

        return $this->created($zone, 'Yard zone created.');
    }

    // ── Dock Doors ────────────────────────────────────────────────────────────

    /**
     * GET /inventory/yard/dock-doors
     */
    public function dockDoors(Request $request): JsonResponse
    {
        $doors = DockDoor::where('organization_id', $request->user()->organization_id)
            ->when($request->input('warehouse_id'), fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->with(['warehouse', 'yardZone'])
            ->orderBy('door_code')
            ->get();

        return $this->success($doors);
    }

    /**
     * POST /inventory/yard/dock-doors
     */
    public function storeDockDoor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'door_code'    => 'required|string|max:10',
            'door_type'    => 'nullable|in:inbound,outbound,combined',
            'yard_zone_id' => 'nullable|integer',
            'is_active'    => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['is_active']       = $validated['is_active'] ?? true;
        $validated['status']          = DockDoor::STATUS_AVAILABLE;

        $door = DockDoor::create($validated);

        return $this->created($door->load('yardZone'), 'Dock door created.');
    }

    /**
     * PUT /inventory/yard/dock-doors/{id}
     */
    public function updateDockDoor(Request $request, int $id): JsonResponse
    {
        $door = DockDoor::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'door_code'    => 'sometimes|string|max:10',
            'door_type'    => 'nullable|in:inbound,outbound,combined',
            'yard_zone_id' => 'nullable|integer',
            'is_active'    => 'nullable|boolean',
            'status'       => 'nullable|in:available,occupied,maintenance',
        ]);

        $door->update($validated);

        return $this->success($door->fresh()->load('yardZone'), 'Dock door updated.');
    }

    // ── Appointments ──────────────────────────────────────────────────────────

    /**
     * GET /inventory/yard/appointments
     */
    public function appointments(Request $request): JsonResponse
    {
        $appointments = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->when($request->input('warehouse_id'), fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('date'), fn ($q, $d) => $q->whereDate('scheduled_arrival', $d))
            ->with(['vendor', 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($appointments);
    }

    /**
     * POST /inventory/yard/appointments
     */
    public function storeAppointment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'       => 'required|integer',
            'appointment_number' => 'required|string|max:20',
            'scheduled_arrival'  => 'required|date',
            'appointment_type'   => 'nullable|in:delivery,pickup,both',
            'vendor_id'          => 'nullable|integer',
            'scheduled_departure' => 'nullable|date|after_or_equal:scheduled_arrival',
            'vehicle_plate'      => 'nullable|string|max:20',
            'driver_name'        => 'nullable|string|max:100',
            'driver_phone'       => 'nullable|string|max:30',
            'reference_type'     => 'nullable|in:purchase_order,sales_order,transfer',
            'reference_id'       => 'nullable|integer',
            'notes'              => 'nullable|string',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by']      = $request->user()->id;

        $appointment = $this->yardService->createAppointment($validated);

        return $this->created($appointment, 'Truck appointment created.');
    }

    /**
     * GET /inventory/yard/appointments/{id}
     */
    public function showAppointment(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->with(['vendor', 'dockDoor', 'yardZone', 'movements', 'creator'])
            ->findOrFail($id);

        return $this->success($appointment);
    }

    /**
     * PUT /inventory/yard/appointments/{id}
     */
    public function updateAppointment(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($appointment->isDeparted() || $appointment->isCancelled()) {
            return $this->success(null, 'Departed or cancelled appointments cannot be updated.', 422);
        }

        $validated = $request->validate([
            'scheduled_arrival'   => 'sometimes|date',
            'scheduled_departure' => 'nullable|date',
            'vendor_id'           => 'nullable|integer',
            'vehicle_plate'       => 'nullable|string|max:20',
            'driver_name'         => 'nullable|string|max:100',
            'driver_phone'        => 'nullable|string|max:30',
            'reference_type'      => 'nullable|in:purchase_order,sales_order,transfer',
            'reference_id'        => 'nullable|integer',
            'notes'               => 'nullable|string',
        ]);

        $appointment->update($validated);

        return $this->success($appointment->fresh(), 'Appointment updated.');
    }

    /**
     * POST /inventory/yard/appointments/{id}/cancel
     */
    public function cancelAppointment(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($appointment->isCancelled()) {
            return $this->success($appointment, 'Appointment is already cancelled.');
        }

        if ($appointment->isDeparted()) {
            return $this->success(null, 'Departed appointments cannot be cancelled.', 422);
        }

        $appointment->update(['status' => TruckAppointment::STATUS_CANCELLED]);

        return $this->success($appointment->fresh(), 'Appointment cancelled.');
    }

    /**
     * POST /inventory/yard/appointments/{id}/check-in
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'actual_arrival' => 'nullable|date',
            'yard_zone_id'   => 'nullable|integer',
            'vehicle_plate'  => 'nullable|string|max:20',
            'driver_name'    => 'nullable|string|max:100',
            'driver_phone'   => 'nullable|string|max:30',
            'notes'          => 'nullable|string',
        ]);

        $validated['moved_by'] = $request->user()->id;

        try {
            $movement = $this->yardService->checkIn($appointment, $validated);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success([
            'appointment' => $appointment->fresh()->load(['dockDoor', 'yardZone']),
            'movement'    => $movement,
        ], 'Truck checked in.');
    }

    /**
     * POST /inventory/yard/appointments/{id}/assign-dock
     */
    public function assignDock(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'dock_door_id' => 'required|integer',
        ]);

        try {
            $movement = $this->yardService->assignToDock($appointment, (int) $validated['dock_door_id']);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success([
            'appointment' => $appointment->fresh()->load(['dockDoor', 'yardZone']),
            'movement'    => $movement,
        ], 'Truck assigned to dock.');
    }

    /**
     * POST /inventory/yard/appointments/{id}/depart
     */
    public function depart(Request $request, int $id): JsonResponse
    {
        $appointment = TruckAppointment::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        try {
            $movement = $this->yardService->depart($appointment);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success([
            'appointment' => $appointment->fresh(),
            'movement'    => $movement,
        ], 'Truck departed.');
    }

    // ── Dashboard / Queries ───────────────────────────────────────────────────

    /**
     * GET /inventory/yard/dock-doors/available
     */
    public function availableDocks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'datetime'     => 'nullable|date',
        ]);

        $doors = $this->yardService->getAvailableDocks(
            (int) $validated['warehouse_id'],
            $validated['datetime'] ?? now()->toDateTimeString()
        );

        return $this->success($doors);
    }

    /**
     * GET /inventory/yard/schedule
     */
    public function dailySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
            'date'         => 'nullable|date',
        ]);

        $schedule = $this->yardService->getDailySchedule(
            (int) $validated['warehouse_id'],
            $validated['date'] ?? now()->toDateString()
        );

        return $this->success($schedule);
    }

    /**
     * GET /inventory/yard/status
     */
    public function yardStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer',
        ]);

        $status = $this->yardService->getYardStatus((int) $validated['warehouse_id']);

        return $this->success($status);
    }
}
