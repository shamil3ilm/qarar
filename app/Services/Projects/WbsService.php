<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\WbsElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WbsService
{
    /**
     * Return the full WBS hierarchy for a project as a nested tree.
     *
     * @return Collection<int, WbsElement>
     */
    public function getHierarchy(int $projectId): Collection
    {
        $all = WbsElement::where('project_id', $projectId)
            ->with('responsible')
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($all, null);
    }

    /**
     * Create a WBS element, auto-deriving level from its parent.
     *
     * @param array{
     *   project_id: int,
     *   parent_id?: int|null,
     *   wbs_code?: string,
     *   name: string,
     *   description?: string,
     *   planned_cost?: float,
     *   planned_revenue?: float,
     *   responsible_employee_id?: int,
     *   sort_order?: int,
     *   status?: string,
     *   planned_start_date?: string,
     *   planned_end_date?: string
     * } $data
     */
    public function createElement(array $data): WbsElement
    {
        return DB::transaction(function () use ($data): WbsElement {
            $parentId = $data['parent_id'] ?? null;
            $level    = $this->deriveLevel($parentId);

            $element = WbsElement::create([
                'organization_id'         => auth()->user()->organization_id,
                'project_id'              => $data['project_id'],
                'parent_id'               => $parentId,
                'wbs_code'                => $data['wbs_code'] ?? '',
                'name'                    => $data['name'],
                'description'             => $data['description'] ?? null,
                'status'                  => $data['status'] ?? WbsElement::STATUS_CREATED,
                'planned_cost'            => $data['planned_cost'] ?? 0,
                'planned_revenue'         => $data['planned_revenue'] ?? 0,
                'actual_cost'             => 0,
                'actual_revenue'          => 0,
                'responsible_employee_id' => $data['responsible_employee_id'] ?? null,
                'sort_order'              => $data['sort_order'] ?? 0,
                'planned_start_date'      => $data['planned_start_date'] ?? null,
                'planned_end_date'        => $data['planned_end_date'] ?? null,
                'progress_percent'        => 0,
            ]);

            return $element->fresh(['project', 'parent', 'children']);
        });
    }

    /**
     * Update a WBS element's mutable fields.
     *
     * @param array<string, mixed> $data
     */
    public function updateElement(WbsElement $element, array $data): WbsElement
    {
        $allowed = [
            'wbs_code', 'name', 'description', 'status',
            'planned_cost', 'planned_revenue',
            'responsible_employee_id', 'sort_order',
            'planned_start_date', 'planned_end_date',
            'progress_percent',
        ];

        $updates = array_intersect_key($data, array_flip($allowed));

        $element->update($updates);

        return $element->fresh(['project', 'parent', 'children']);
    }

    /**
     * Aggregate child element costs up to parent levels (bottom-up rollup).
     * Updates actual_cost and planned_cost on each non-leaf WBS element.
     */
    public function rollupCosts(int $projectId): void
    {
        DB::transaction(function () use ($projectId): void {
            // Process deepest elements first (leaves → roots)
            $elements = WbsElement::where('project_id', $projectId)
                ->with('children')
                ->get()
                ->sortByDesc(fn (WbsElement $el) => $this->countDepth($el));

            foreach ($elements as $element) {
                if (!$element->hasChildren()) {
                    continue;
                }

                $element->children->loadMissing('children');

                $totalPlanned = $element->children->sum(fn (WbsElement $child) => (float) $child->planned_cost);
                $totalActual  = $element->children->sum(fn (WbsElement $child) => (float) $child->actual_cost);

                $element->update([
                    'planned_cost' => $totalPlanned,
                    'actual_cost'  => $totalActual,
                ]);
            }
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build a nested tree from a flat collection, grouped by parent_id.
     *
     * @param  Collection<int, WbsElement>  $all
     * @return Collection<int, WbsElement>
     */
    private function buildTree(Collection $all, ?int $parentId): Collection
    {
        return $all
            ->filter(fn (WbsElement $el) => $el->parent_id === $parentId)
            ->values()
            ->each(function (WbsElement $el) use ($all): void {
                $el->setRelation('children', $this->buildTree($all, $el->id));
            });
    }

    /**
     * Derive level: root = 1, each parent adds 1.
     */
    private function deriveLevel(?int $parentId): int
    {
        if ($parentId === null) {
            return 1;
        }

        $parent = WbsElement::find($parentId);

        return $parent !== null ? ((int) ($parent->getAttribute('level') ?? 1)) + 1 : 1;
    }

    /**
     * Count depth of a WBS element by walking up the parent chain.
     */
    private function countDepth(WbsElement $element): int
    {
        $depth  = 0;
        $cursor = $element;

        while ($cursor->parent_id !== null) {
            $depth++;
            $cursor = $cursor->parent ?? $element; // guard infinite loop
            if ($depth > 20) {
                break;
            }
        }

        return $depth;
    }
}
