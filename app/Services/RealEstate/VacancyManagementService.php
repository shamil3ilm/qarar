<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\Building;
use App\Models\RealEstate\LeaseContract;
use App\Models\RealEstate\OccupancySnapshot;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\RentalUnit;
use App\Models\RealEstate\VacancyPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RE-FX Vacancy Management Service.
 *
 * Tracks vacant periods per rental unit, computes occupancy rates,
 * and generates occupancy snapshots for reporting.
 *
 * SAP RE-FX equivalent: vacancy control object + RE reporting node.
 */
class VacancyManagementService
{
    /**
     * Open a vacancy period when a unit becomes vacant.
     * Called on lease expiry, early termination, or unit creation.
     */
    public function openVacancy(
        RentalUnit $unit,
        string $vacantFrom,
        string $reason,
        ?float $marketRent = null,
        ?string $notes = null,
    ): VacancyPeriod {
        // Close any open period first (guard against duplicates)
        $this->closeOpenPeriod($unit, $vacantFrom);

        $vacancy = VacancyPeriod::create([
            'organization_id' => $unit->organization_id,
            'rental_unit_id'  => $unit->id,
            'building_id'     => $unit->building_id,
            'property_id'     => $unit->building?->property_id ?? null,
            'portfolio_id'    => $unit->building?->property?->portfolio_id ?? null,
            'vacant_from'     => $vacantFrom,
            'vacant_to'       => null,
            'vacancy_reason'  => $reason,
            'market_rent'     => $marketRent,
            'notes'           => $notes,
        ]);

        $unit->update(['status' => 'vacant']);

        return $vacancy;
    }

    /**
     * Close the open vacancy period when a new lease starts.
     */
    public function closeVacancy(RentalUnit $unit, string $occupiedFrom): ?VacancyPeriod
    {
        return $this->closeOpenPeriod($unit, $occupiedFrom);
    }

    // ----------------------------------------------------------------
    // Occupancy snapshots
    // ----------------------------------------------------------------

    /**
     * Take a current occupancy snapshot for a building.
     */
    public function snapshotBuilding(Building $building, ?string $date = null): OccupancySnapshot
    {
        $snapDate = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();

        $units = RentalUnit::where('building_id', $building->id)
            ->where('is_active', true)
            ->get();

        $occupied      = $units->where('status', 'occupied');
        $vacant        = $units->where('status', 'vacant');
        $totalArea     = (float) $units->sum('area_sqm');
        $occupiedArea  = (float) $occupied->sum('area_sqm');
        $occupancyRate = $units->count() > 0 ? round($occupied->count() / $units->count() * 100, 2) : 0.0;
        $areaRate      = $totalArea > 0 ? round($occupiedArea / $totalArea * 100, 2) : 0.0;

        $potentialRent = (float) LeaseContract::whereIn('rental_unit_id', $units->pluck('id'))
            ->where('status', 'active')
            ->orWhere(function ($q) use ($units) {
                $q->whereIn('rental_unit_id', $units->pluck('id'));
            })
            ->where(function ($q) use ($snapDate) {
                $q->where('start_date', '<=', $snapDate)->where('end_date', '>=', $snapDate);
            })
            ->sum('monthly_rent');

        return OccupancySnapshot::updateOrCreate(
            [
                'organization_id' => $building->organization_id,
                'snapshot_type'   => OccupancySnapshot::TYPE_BUILDING,
                'reference_id'    => $building->id,
                'snapshot_date'   => $snapDate,
            ],
            [
                'total_units'         => $units->count(),
                'occupied_units'      => $occupied->count(),
                'vacant_units'        => $vacant->count(),
                'occupancy_rate'      => $occupancyRate,
                'total_area_sqm'      => $totalArea,
                'occupied_area_sqm'   => $occupiedArea,
                'area_occupancy_rate' => $areaRate,
                'potential_rent'      => $potentialRent,
                'actual_rent'         => $potentialRent,
            ]
        );
    }

    /**
     * Take occupancy snapshots for all buildings in a portfolio.
     */
    public function snapshotPortfolio(Portfolio $portfolio, ?string $date = null): array
    {
        $buildings = Building::whereHas('property', fn ($q) => $q->where('portfolio_id', $portfolio->id))
            ->where('organization_id', $portfolio->organization_id)
            ->get();

        $snapshots = [];
        foreach ($buildings as $building) {
            $snapshots[] = $this->snapshotBuilding($building, $date);
        }

        return $snapshots;
    }

    /**
     * Get vacancy history for a unit.
     */
    public function getVacancyHistory(RentalUnit $unit): \Illuminate\Support\Collection
    {
        return VacancyPeriod::where('rental_unit_id', $unit->id)
            ->orderByDesc('vacant_from')
            ->get()
            ->map(function (VacancyPeriod $v) {
                $v->days_vacant    = $v->getDaysVacant();
                $v->vacancy_loss   = $v->computeVacancyLoss();
                return $v;
            });
    }

    /**
     * Vacancy report for a building: all vacant units with open periods.
     */
    public function getVacantUnits(int $buildingId, int $organizationId): \Illuminate\Support\Collection
    {
        return VacancyPeriod::where('organization_id', $organizationId)
            ->where('building_id', $buildingId)
            ->whereNull('vacant_to')
            ->with('rentalUnit:id,code,name,unit_type,area_sqm,floor_id')
            ->get()
            ->map(function (VacancyPeriod $v) {
                $v->days_vacant  = $v->getDaysVacant();
                $v->vacancy_loss = $v->computeVacancyLoss();
                return $v;
            });
    }

    /**
     * Historical occupancy trend for a building (monthly snapshots).
     */
    public function getOccupancyTrend(int $buildingId, int $organizationId, string $from, string $to): \Illuminate\Support\Collection
    {
        return OccupancySnapshot::where('organization_id', $organizationId)
            ->where('snapshot_type', OccupancySnapshot::TYPE_BUILDING)
            ->where('reference_id', $buildingId)
            ->whereBetween('snapshot_date', [$from, $to])
            ->orderBy('snapshot_date')
            ->get();
    }

    // ----------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------

    private function closeOpenPeriod(RentalUnit $unit, string $closedAt): ?VacancyPeriod
    {
        $open = VacancyPeriod::where('rental_unit_id', $unit->id)->whereNull('vacant_to')->first();

        if (! $open) {
            return null;
        }

        $loss = $open->computeVacancyLoss();
        $open->update([
            'vacant_to'    => $closedAt,
            'vacancy_loss' => $loss,
        ]);

        $unit->update(['status' => 'occupied']);

        return $open->fresh();
    }
}
