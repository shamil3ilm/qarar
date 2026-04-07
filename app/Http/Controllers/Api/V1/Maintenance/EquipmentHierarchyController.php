<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\FunctionalLocation;
use App\Services\Maintenance\EquipmentHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Equipment Hierarchy — SAP PM IL01/IE01 equivalent.
 * Functional location tree + equipment installation/deinstallation.
 */
class EquipmentHierarchyController extends Controller
{
    public function __construct(private readonly EquipmentHierarchyService $service) {}

    /**
     * GET /equipment-hierarchy/tree
     * Returns full FLOC tree with installed equipment.
     */
    public function tree(Request $request): JsonResponse
    {
        $tree = $this->service->buildTree(
            organizationId: $request->user()->organization_id,
            rootId:         $request->root_id ? (int) $request->root_id : null,
        );

        return $this->successResponse($tree, 'Equipment hierarchy tree retrieved');
    }

    /**
     * POST /equipment-hierarchy/install
     * Install equipment at a functional location.
     */
    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id'           => ['required', 'integer'],
            'functional_location_id' => ['required', 'integer'],
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);
        $floc      = FunctionalLocation::findOrFail($data['functional_location_id']);

        $equipment = $this->service->install($equipment, $floc);

        return $this->successResponse($equipment, 'Equipment installed at functional location');
    }

    /**
     * POST /equipment-hierarchy/deinstall
     * Remove equipment from its functional location.
     */
    public function deinstall(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer'],
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);
        $equipment = $this->service->deinstall($equipment);

        return $this->successResponse($equipment, 'Equipment deinstalled');
    }

    /**
     * POST /equipment-hierarchy/relocate
     * Move equipment between functional locations.
     */
    public function relocate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id'           => ['required', 'integer'],
            'functional_location_id' => ['required', 'integer'],
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);
        $floc      = FunctionalLocation::findOrFail($data['functional_location_id']);

        $equipment = $this->service->relocate($equipment, $floc);

        return $this->successResponse($equipment, 'Equipment relocated');
    }

    /**
     * GET /equipment-hierarchy/floc/{floc}/equipment
     * All equipment under a FLOC and its descendants.
     */
    public function underFloc(FunctionalLocation $functionalLocation): JsonResponse
    {
        $equipment = $this->service->getEquipmentUnderFloc($functionalLocation);

        return $this->successResponse($equipment, 'Equipment under functional location retrieved');
    }

    /**
     * GET /equipment-hierarchy/where-used/{equipment}
     * Trace equipment location path from current FLOC to plant root.
     */
    public function whereUsed(Equipment $equipment): JsonResponse
    {
        $result = $this->service->whereUsed($equipment);

        return $this->successResponse($result, 'Equipment where-used retrieved');
    }

    /**
     * GET /equipment-hierarchy/utilisation-summary
     * Active/inactive equipment count per FLOC.
     */
    public function utilisationSummary(Request $request): JsonResponse
    {
        $summary = $this->service->utilisationSummary($request->user()->organization_id);

        return $this->successResponse($summary, 'Equipment utilisation summary');
    }
}
