<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\FuelLog;
use App\Models\Maintenance\MileageLog;
use App\Models\Maintenance\Vehicle;
use App\Models\Maintenance\VehicleAssignment;
use App\Models\Maintenance\VehicleMaintenanceRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetService
{
    /**
     * Create a new vehicle record.
     */
    public function create(array $data): Vehicle
    {
        return Vehicle::create([
            'organization_id'     => auth()->user()->organization_id,
            'fleet_number'        => $data['fleet_number'],
            'license_plate'       => $data['license_plate'],
            'make'                => $data['make'],
            'model'               => $data['model'],
            'year'                => $data['year'],
            'vin'                 => $data['vin'] ?? null,
            'vehicle_type'        => $data['vehicle_type'] ?? Vehicle::TYPE_CAR,
            'fuel_type'           => $data['fuel_type'] ?? Vehicle::FUEL_PETROL,
            'color'               => $data['color'] ?? null,
            'department_id'       => $data['department_id'] ?? null,
            'current_mileage_km'  => $data['current_mileage_km'] ?? 0,
            'last_service_km'     => $data['last_service_km'] ?? null,
            'next_service_km'     => $data['next_service_km'] ?? null,
            'insurance_expiry'    => $data['insurance_expiry'] ?? null,
            'registration_expiry' => $data['registration_expiry'] ?? null,
            'is_active'           => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Assign a driver to a vehicle, ending any current assignment first.
     */
    public function assignDriver(int $vehicleId, int $driverId, array $data): VehicleAssignment
    {
        return DB::transaction(function () use ($vehicleId, $driverId, $data): VehicleAssignment {
            $orgId = auth()->user()->organization_id;

            // End any active assignment for this vehicle
            VehicleAssignment::where('vehicle_id', $vehicleId)
                ->where('is_current', true)
                ->update([
                    'is_current'  => false,
                    'assigned_to' => now(),
                ]);

            return VehicleAssignment::create([
                'organization_id' => $orgId,
                'vehicle_id'      => $vehicleId,
                'driver_id'       => $driverId,
                'assigned_from'   => $data['assigned_from'] ?? now(),
                'assigned_to'     => $data['assigned_to'] ?? null,
                'purpose'         => $data['purpose'] ?? null,
                'is_current'      => true,
            ]);
        });
    }

    /**
     * End the current driver assignment for a vehicle.
     */
    public function unassignDriver(int $vehicleId): void
    {
        VehicleAssignment::where('vehicle_id', $vehicleId)
            ->where('is_current', true)
            ->update([
                'is_current'  => false,
                'assigned_to' => now(),
            ]);
    }

    /**
     * Log a mileage trip for a vehicle and update its odometer.
     */
    public function logMileage(array $data): MileageLog
    {
        return DB::transaction(function () use ($data): MileageLog {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            $distanceKm = (int) $data['odometer_end'] - (int) $data['odometer_start'];

            $log = MileageLog::create([
                'organization_id' => auth()->user()->organization_id,
                'vehicle_id'      => $data['vehicle_id'],
                'log_date'        => $data['log_date'] ?? now()->toDateString(),
                'odometer_start'  => $data['odometer_start'],
                'odometer_end'    => $data['odometer_end'],
                'distance_km'     => max(0, $distanceKm),
                'trip_purpose'    => $data['trip_purpose'] ?? null,
                'driver_id'       => $data['driver_id'] ?? null,
                'route'           => $data['route'] ?? null,
            ]);

            // Update vehicle odometer if this reading is higher
            if ((int) $data['odometer_end'] > $vehicle->current_mileage_km) {
                $vehicle->update(['current_mileage_km' => (int) $data['odometer_end']]);
            }

            return $log;
        });
    }

    /**
     * Log a fuel fill-up for a vehicle.
     */
    public function logFuel(array $data): FuelLog
    {
        return FuelLog::create([
            'organization_id'      => auth()->user()->organization_id,
            'vehicle_id'           => $data['vehicle_id'],
            'log_date'             => $data['log_date'] ?? now()->toDateString(),
            'odometer_reading'     => $data['odometer_reading'],
            'fuel_quantity_liters' => $data['fuel_quantity_liters'],
            'fuel_cost'            => $data['fuel_cost'],
            'currency_code'        => $data['currency_code'],
            'fuel_type'            => $data['fuel_type'],
            'station'              => $data['station'] ?? null,
            'filled_by'            => $data['filled_by'] ?? null,
        ]);
    }

    /**
     * Record a maintenance event for a vehicle.
     */
    public function recordMaintenance(array $data): VehicleMaintenanceRecord
    {
        return DB::transaction(function () use ($data): VehicleMaintenanceRecord {
            $record = VehicleMaintenanceRecord::create([
                'organization_id'       => auth()->user()->organization_id,
                'vehicle_id'            => $data['vehicle_id'],
                'maintenance_type'      => $data['maintenance_type'],
                'service_date'          => $data['service_date'],
                'odometer_reading'      => $data['odometer_reading'] ?? null,
                'description'           => $data['description'],
                'cost'                  => $data['cost'] ?? null,
                'currency_code'         => $data['currency_code'] ?? null,
                'service_provider'      => $data['service_provider'] ?? null,
                'next_service_date'     => $data['next_service_date'] ?? null,
                'next_service_km'       => $data['next_service_km'] ?? null,
                'maintenance_order_id'  => $data['maintenance_order_id'] ?? null,
            ]);

            // Update vehicle service trackers
            $updateData = [];
            if (!empty($data['odometer_reading'])) {
                $updateData['last_service_km'] = (int) $data['odometer_reading'];
            }
            if (!empty($data['next_service_km'])) {
                $updateData['next_service_km'] = (int) $data['next_service_km'];
            }
            if (!empty($updateData)) {
                Vehicle::where('id', $data['vehicle_id'])->update($updateData);
            }

            return $record;
        });
    }

    /**
     * Get vehicles that have exceeded or are near their service mileage threshold.
     */
    public function getVehiclesRequiringService(int $organizationId): Collection
    {
        return Vehicle::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereNotNull('next_service_km')
            ->whereColumn('current_mileage_km', '>=', 'next_service_km')
            ->get();
    }

    /**
     * Summarise fleet fuel and maintenance costs over a date range.
     *
     * @return array{fuel_cost: float, maintenance_cost: float, total_cost: float, vehicle_count: int}
     */
    public function getFleetCostSummary(int $organizationId, string $dateFrom, string $dateTo): array
    {
        $fuelCost = FuelLog::where('organization_id', $organizationId)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->sum('fuel_cost');

        $maintenanceCost = VehicleMaintenanceRecord::where('organization_id', $organizationId)
            ->whereBetween('service_date', [$dateFrom, $dateTo])
            ->whereNotNull('cost')
            ->sum('cost');

        $vehicleCount = Vehicle::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();

        return [
            'fuel_cost'        => round((float) $fuelCost, 4),
            'maintenance_cost' => round((float) $maintenanceCost, 4),
            'total_cost'       => round((float) $fuelCost + (float) $maintenanceCost, 4),
            'vehicle_count'    => $vehicleCount,
        ];
    }

    /**
     * Calculate average fuel efficiency (km/L) for a vehicle over a date range.
     */
    public function getFuelEfficiency(int $vehicleId, string $dateFrom, string $dateTo): float
    {
        $logs = FuelLog::where('vehicle_id', $vehicleId)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->orderBy('log_date')
            ->get();

        if ($logs->count() < 2) {
            return 0.0;
        }

        $totalLiters = (float) $logs->sum('fuel_quantity_liters');

        if ($totalLiters === 0.0) {
            return 0.0;
        }

        $firstOdometer = (int) $logs->first()->odometer_reading;
        $lastOdometer  = (int) $logs->last()->odometer_reading;
        $totalKm       = $lastOdometer - $firstOdometer;

        if ($totalKm <= 0) {
            return 0.0;
        }

        return round($totalKm / $totalLiters, 2);
    }
}
