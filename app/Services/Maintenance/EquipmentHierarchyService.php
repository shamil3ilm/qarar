<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\FunctionalLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Equipment Hierarchy — SAP PM IL01/IE01 equivalent.
 *
 * Builds and manages the Functional Location ↔ Equipment installation tree.
 *
 * SAP concepts mapped:
 *  - Functional Location (FLOC) → FunctionalLocation model (plant/area/line/machine levels)
 *  - Equipment Installation     → install() links Equipment to a FLOC
 *  - Equipment Deinstallation   → deinstall() unlinks Equipment from FLOC
 *  - Hierarchy tree             → buildTree() returns nested array
 *  - Where-Used                 → whereUsed() traces equipment through locations
 */
class EquipmentHierarchyService
{
    /**
     * Return the full FLOC hierarchy tree from roots, with equipment at each node.
     *
     * @return array<int, array{floc: FunctionalLocation, children: array, equipment: Collection}>
     */
    public function buildTree(int $organizationId, ?int $rootId = null): array
    {
        if ($rootId) {
            $roots = FunctionalLocation::where('organization_id', $organizationId)
                ->where('id', $rootId)
                ->with(['equipment.category'])
                ->get();
        } else {
            $roots = FunctionalLocation::where('organization_id', $organizationId)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['equipment.category'])
                ->get();
        }

        return $this->buildNodes($roots, $organizationId);
    }

    /**
     * Install (assign) equipment to a functional location.
     * Equipment can only be installed at one FLOC at a time.
     */
    public function install(Equipment $equipment, FunctionalLocation $floc): Equipment
    {
        if ($equipment->organization_id !== $floc->organization_id) {
            throw new \RuntimeException('Equipment and functional location must belong to the same organization.');
        }

        $equipment->update(['functional_location_id' => $floc->id]);

        return $equipment->fresh('functionalLocation');
    }

    /**
     * Deinstall equipment — remove it from its current functional location.
     */
    public function deinstall(Equipment $equipment): Equipment
    {
        $equipment->update(['functional_location_id' => null]);

        return $equipment->fresh();
    }

    /**
     * Move equipment from one FLOC to another in a single operation.
     */
    public function relocate(Equipment $equipment, FunctionalLocation $targetFloc): Equipment
    {
        return $this->install($equipment, $targetFloc);
    }

    /**
     * Return all equipment installed at a FLOC and its descendants.
     */
    public function getEquipmentUnderFloc(FunctionalLocation $floc): Collection
    {
        $flocIds = $this->collectDescendantIds($floc);

        return Equipment::whereIn('functional_location_id', $flocIds)
            ->with(['category', 'functionalLocation'])
            ->get();
    }

    /**
     * Where-used report for a piece of equipment:
     * returns current FLOC and its ancestors up to the plant level.
     */
    public function whereUsed(Equipment $equipment): array
    {
        if (! $equipment->functional_location_id) {
            return ['equipment' => $equipment, 'location_path' => []];
        }

        $path = [];
        $floc = $equipment->functionalLocation;

        while ($floc) {
            array_unshift($path, $floc->only(['id', 'uuid', 'code', 'name', 'location_type']));
            $floc = $floc->parent_id ? FunctionalLocation::find($floc->parent_id) : null;
        }

        return [
            'equipment'     => $equipment->only(['id', 'uuid', 'equipment_number', 'name', 'status']),
            'location_path' => $path,
        ];
    }

    /**
     * Hierarchy utilisation summary — how many active vs inactive equipment per FLOC.
     */
    public function utilisationSummary(int $organizationId): array
    {
        $rows = Equipment::select('functional_location_id', 'status', DB::raw('count(*) as cnt'))
            ->where('organization_id', $organizationId)
            ->groupBy('functional_location_id', 'status')
            ->get();

        $flocIds = $rows->pluck('functional_location_id')->filter()->unique();
        $flocs   = FunctionalLocation::whereIn('id', $flocIds)->get()->keyBy('id');

        $summary = [];
        foreach ($rows as $row) {
            $flocId = $row->functional_location_id ?? 'unassigned';
            if (! isset($summary[$flocId])) {
                $floc             = $row->functional_location_id ? $flocs->get($row->functional_location_id) : null;
                $summary[$flocId] = [
                    'floc_id'   => $row->functional_location_id,
                    'floc_name' => $floc?->name ?? 'Unassigned',
                    'total'     => 0,
                    'active'    => 0,
                    'inactive'  => 0,
                ];
            }
            $summary[$flocId]['total']                                         += $row->cnt;
            $summary[$flocId][$row->status === Equipment::STATUS_ACTIVE ? 'active' : 'inactive'] += $row->cnt;
        }

        return array_values($summary);
    }

    // ----------------------------------------------------------------

    private function buildNodes(Collection $flocs, int $organizationId): array
    {
        $nodes = [];

        foreach ($flocs as $floc) {
            $children = FunctionalLocation::where('organization_id', $organizationId)
                ->where('parent_id', $floc->id)
                ->where('is_active', true)
                ->with(['equipment.category'])
                ->get();

            $nodes[] = [
                'floc'      => $floc->only(['id', 'uuid', 'code', 'name', 'location_type']),
                'equipment' => $floc->equipment->map(fn (Equipment $e) => $e->only([
                    'id', 'uuid', 'equipment_number', 'name', 'status', 'next_maintenance_date',
                ])),
                'children'  => $this->buildNodes($children, $organizationId),
            ];
        }

        return $nodes;
    }

    private function collectDescendantIds(FunctionalLocation $floc): array
    {
        $ids   = [$floc->id];
        $queue = [$floc->id];

        while (! empty($queue)) {
            $children = FunctionalLocation::whereIn('parent_id', $queue)->pluck('id')->toArray();
            $ids      = array_merge($ids, $children);
            $queue    = $children;
        }

        return $ids;
    }
}
