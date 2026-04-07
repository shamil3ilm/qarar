<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\DockDoor;
use App\Models\Inventory\TruckAppointment;
use App\Models\Inventory\YardMovement;
use App\Models\Inventory\YardZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class YardManagementService
{
    /**
     * Create a truck appointment.
     *
     * Expected keys in $data:
     *   organization_id, warehouse_id, appointment_number, scheduled_arrival,
     *   appointment_type (delivery/pickup/both), vendor_id?, scheduled_departure?,
     *   vehicle_plate?, driver_name?, driver_phone?, reference_type?,
     *   reference_id?, notes?, created_by?
     */
    public function createAppointment(array $data): TruckAppointment
    {
        $data['status'] = TruckAppointment::STATUS_SCHEDULED;

        return TruckAppointment::create($data);
    }

    /**
     * Check in a truck: set actual_arrival, update status to checked_in,
     * assign to a yard zone if provided, and create an arrival movement.
     */
    public function checkIn(TruckAppointment $appointment, array $data): YardMovement
    {
        if (!$appointment->canCheckIn()) {
            throw new RuntimeException(
                "Appointment cannot be checked in. Current status: {$appointment->status}."
            );
        }

        return DB::transaction(function () use ($appointment, $data): YardMovement {
            $yardZoneId = $data['yard_zone_id'] ?? null;

            $appointment->update([
                'status'         => TruckAppointment::STATUS_CHECKED_IN,
                'actual_arrival' => $data['actual_arrival'] ?? now(),
                'yard_zone_id'   => $yardZoneId,
                'vehicle_plate'  => $data['vehicle_plate'] ?? $appointment->vehicle_plate,
                'driver_name'    => $data['driver_name'] ?? $appointment->driver_name,
                'driver_phone'   => $data['driver_phone'] ?? $appointment->driver_phone,
            ]);

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'to_zone_id'           => $yardZoneId,
                'movement_type'        => YardMovement::TYPE_ARRIVAL,
                'moved_at'             => $data['actual_arrival'] ?? now(),
                'moved_by'             => $data['moved_by'] ?? null,
                'notes'                => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Assign a checked-in truck to a dock door.
     */
    public function assignToDock(TruckAppointment $appointment, int $dockDoorId): YardMovement
    {
        if (!$appointment->canAssignDock()) {
            throw new RuntimeException(
                "Appointment cannot be assigned to a dock. Current status: {$appointment->status}."
            );
        }

        $dockDoor = DockDoor::findOrFail($dockDoorId);

        if (!$dockDoor->isAvailable()) {
            throw new RuntimeException("Dock door #{$dockDoorId} is not available.");
        }

        return DB::transaction(function () use ($appointment, $dockDoor): YardMovement {
            $previousZoneId = $appointment->yard_zone_id;

            $appointment->update([
                'status'       => TruckAppointment::STATUS_DOCKED,
                'dock_door_id' => $dockDoor->id,
            ]);

            $dockDoor->update(['status' => DockDoor::STATUS_OCCUPIED]);

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'from_zone_id'         => $previousZoneId,
                'to_dock_id'           => $dockDoor->id,
                'movement_type'        => YardMovement::TYPE_MOVE_TO_DOCK,
                'moved_at'             => now(),
                'moved_by'             => auth()->id(),
            ]);
        });
    }

    /**
     * Mark a truck as departed and free the dock door.
     */
    public function depart(TruckAppointment $appointment): YardMovement
    {
        if (!$appointment->canDepart()) {
            throw new RuntimeException(
                "Appointment cannot be departed. Current status: {$appointment->status}."
            );
        }

        return DB::transaction(function () use ($appointment): YardMovement {
            $dockDoorId = $appointment->dock_door_id;

            $appointment->update([
                'status'          => TruckAppointment::STATUS_DEPARTED,
                'actual_departure' => now(),
            ]);

            if ($dockDoorId !== null) {
                DockDoor::where('id', $dockDoorId)
                    ->update(['status' => DockDoor::STATUS_AVAILABLE]);
            }

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'from_dock_id'         => $dockDoorId,
                'from_zone_id'         => $appointment->yard_zone_id,
                'movement_type'        => YardMovement::TYPE_DEPARTURE,
                'moved_at'             => now(),
                'moved_by'             => auth()->id(),
            ]);
        });
    }

    /**
     * Get available dock doors for a warehouse at a given datetime.
     * A door is available if it has no overlapping active appointment.
     */
    public function getAvailableDocks(int $warehouseId, string $datetime): Collection
    {
        $occupiedDoorIds = TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereNotIn('status', [TruckAppointment::STATUS_DEPARTED, TruckAppointment::STATUS_CANCELLED])
            ->whereNotNull('dock_door_id')
            ->pluck('dock_door_id')
            ->all();

        return DockDoor::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->where('status', DockDoor::STATUS_AVAILABLE)
            ->whereNotIn('id', $occupiedDoorIds)
            ->with('yardZone')
            ->get();
    }

    /**
     * Get the daily schedule for a warehouse on a given date.
     */
    public function getDailySchedule(int $warehouseId, string $date): Collection
    {
        return TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereDate('scheduled_arrival', $date)
            ->whereNot('status', TruckAppointment::STATUS_CANCELLED)
            ->with(['vendor', 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->get();
    }

    /**
     * Get current yard status: zone occupancy and dock door statuses.
     */
    public function getYardStatus(int $warehouseId): array
    {
        $zones = YardZone::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->withCount([
                'movements as current_vehicles' => fn ($q) => $q
                    ->whereHas('truckAppointment', fn ($q2) => $q2
                        ->whereNotIn('status', [
                            TruckAppointment::STATUS_DEPARTED,
                            TruckAppointment::STATUS_CANCELLED,
                        ])
                        ->where('yard_zone_id', DB::raw('to_zone_id'))
                    ),
            ])
            ->get();

        $dockDoors = DockDoor::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->with([
                'appointments' => fn ($q) => $q
                    ->whereNotIn('status', [
                        TruckAppointment::STATUS_DEPARTED,
                        TruckAppointment::STATUS_CANCELLED,
                    ])
                    ->latest('actual_arrival')
                    ->limit(1),
            ])
            ->get();

        $activeAppointments = TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereNotIn('status', [TruckAppointment::STATUS_DEPARTED, TruckAppointment::STATUS_CANCELLED])
            ->with(['vendor', 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->get();

        return [
            'warehouse_id'        => $warehouseId,
            'zones'               => $zones,
            'dock_doors'          => $dockDoors,
            'active_appointments' => $activeAppointments,
            'summary'             => [
                'total_zones'            => $zones->count(),
                'total_docks'            => $dockDoors->count(),
                'available_docks'        => $dockDoors->where('status', DockDoor::STATUS_AVAILABLE)->count(),
                'occupied_docks'         => $dockDoors->where('status', DockDoor::STATUS_OCCUPIED)->count(),
                'maintenance_docks'      => $dockDoors->where('status', DockDoor::STATUS_MAINTENANCE)->count(),
                'active_trucks'          => $activeAppointments->count(),
            ],
        ];
    }
}
