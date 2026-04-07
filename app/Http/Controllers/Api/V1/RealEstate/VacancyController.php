<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Http\Controllers\Controller;
use App\Models\RealEstate\Building;
use App\Models\RealEstate\RentalUnit;
use App\Services\RealEstate\VacancyManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RE-FX Vacancy Management Controller.
 *
 * POST /real-estate/units/{id}/vacate          open vacancy
 * POST /real-estate/units/{id}/occupy          close vacancy
 * GET  /real-estate/units/{id}/vacancy-history
 * GET  /real-estate/buildings/{id}/vacant-units
 * GET  /real-estate/buildings/{id}/occupancy-trend
 * POST /real-estate/buildings/{id}/snapshot    take occupancy snapshot
 */
class VacancyController extends Controller
{
    public function __construct(
        private readonly VacancyManagementService $service,
    ) {}

    public function vacate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'vacant_from'  => 'required|date',
            'reason'       => 'required|string|in:lease_expired,early_termination,new_unit,renovation,owner_use',
            'market_rent'  => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $unit    = RentalUnit::findOrFail($id);
        $vacancy = $this->service->openVacancy(
            $unit,
            $validated['vacant_from'],
            $validated['reason'],
            $validated['market_rent'] ?? null,
            $validated['notes'] ?? null,
        );

        return $this->successResponse($vacancy, 'Vacancy period opened', 201);
    }

    public function occupy(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'occupied_from' => 'required|date',
        ]);

        $unit    = RentalUnit::findOrFail($id);
        $closed  = $this->service->closeVacancy($unit, $validated['occupied_from']);

        return $this->successResponse($closed, 'Vacancy period closed');
    }

    public function vacancyHistory(string $id): JsonResponse
    {
        $unit    = RentalUnit::findOrFail($id);
        $history = $this->service->getVacancyHistory($unit);

        return $this->successResponse($history);
    }

    public function vacantUnits(Request $request, string $buildingId): JsonResponse
    {
        $units = $this->service->getVacantUnits((int) $buildingId, $request->user()->organization_id);

        return $this->successResponse($units);
    }

    public function occupancyTrend(Request $request, string $buildingId): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $trend = $this->service->getOccupancyTrend(
            (int) $buildingId,
            $request->user()->organization_id,
            $validated['from'],
            $validated['to'],
        );

        return $this->successResponse($trend);
    }

    public function snapshot(Request $request, string $buildingId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $building = Building::findOrFail($buildingId);
        $snapshot = $this->service->snapshotBuilding($building, $validated['date'] ?? null);

        return $this->successResponse($snapshot, 'Occupancy snapshot taken');
    }
}
