<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\NetworkActivity;
use App\Models\Projects\NetworkActivityRelationship;
use Illuminate\Support\Collection;

class CriticalPathService
{
    /**
     * Run full CPM on all activities of a project.
     *
     * Because the model casts earliest_start / latest_start / earliest_finish /
     * latest_finish as Carbon dates, the forward/backward pass works with plain
     * integer offsets (hours) stored in transient public properties.  Only
     * float_days (a decimal column) is persisted back.
     *
     * Returns activities sorted by ES offset, with critical path IDs flagged.
     */
    public function calculate(int $projectId): array
    {
        $activities = NetworkActivity::query()
            ->where('project_id', $projectId)
            ->get()
            ->keyBy('id');

        if ($activities->isEmpty()) {
            return ['activities' => [], 'critical_path' => [], 'project_duration' => 0];
        }

        $relationships = NetworkActivityRelationship::query()
            ->whereIn('predecessor_activity_id', $activities->keys())
            ->orWhereIn('successor_activity_id', $activities->keys())
            ->get();

        $successors   = $this->buildSuccessorMap($activities, $relationships);
        $predecessors = $this->buildPredecessorMap($activities, $relationships);

        $sorted = $this->topologicalSort($activities->keys()->toArray(), $successors);

        // Use plain arrays for CPM offsets — avoids interfering with Carbon casts.
        $es = [];
        $ef = [];
        $ls = [];
        $lf = [];

        foreach ($activities->keys() as $id) {
            $es[$id] = 0;
            $ef[$id] = 0;
        }

        // ── Forward pass ──────────────────────────────────────────────────────
        foreach ($sorted as $actId) {
            $act      = $activities[$actId];
            $duration = (int) round((float) ($act->planned_work ?? 0));
            $esVal    = 0;

            foreach ($predecessors[$actId] ?? [] as $rel) {
                $predId = $rel['predecessor_activity_id'];
                $lag    = (int) ($rel['lag_days'] ?? 0);

                $esVal = match ($rel['relationship_type']) {
                    NetworkActivityRelationship::TYPE_FINISH_TO_START  => max($esVal, $ef[$predId] + $lag),
                    NetworkActivityRelationship::TYPE_START_TO_START   => max($esVal, $es[$predId] + $lag),
                    NetworkActivityRelationship::TYPE_FINISH_TO_FINISH => max($esVal, $ef[$predId] - $duration + $lag),
                    NetworkActivityRelationship::TYPE_START_TO_FINISH  => max($esVal, $es[$predId] - $duration + $lag),
                    default                                            => max($esVal, $ef[$predId] + $lag),
                };
            }

            $es[$actId] = $esVal;
            $ef[$actId] = $esVal + $duration;
        }

        $projectEnd = empty($ef) ? 0 : max($ef);

        // ── Backward pass ─────────────────────────────────────────────────────
        foreach ($activities->keys() as $id) {
            $lf[$id] = $projectEnd;
            $ls[$id] = $projectEnd;
        }

        foreach (array_reverse($sorted) as $actId) {
            $act      = $activities[$actId];
            $duration = (int) round((float) ($act->planned_work ?? 0));
            $lfVal    = $projectEnd;

            foreach ($successors[$actId] ?? [] as $rel) {
                $succId = $rel['successor_activity_id'];
                $lag    = (int) ($rel['lag_days'] ?? 0);

                $lfVal = match ($rel['relationship_type']) {
                    NetworkActivityRelationship::TYPE_FINISH_TO_START  => min($lfVal, $ls[$succId] - $lag),
                    NetworkActivityRelationship::TYPE_START_TO_START   => min($lfVal, $ls[$succId] + $duration - $lag),
                    NetworkActivityRelationship::TYPE_FINISH_TO_FINISH => min($lfVal, $lf[$succId] - $lag),
                    NetworkActivityRelationship::TYPE_START_TO_FINISH  => min($lfVal, $lf[$succId] + $duration - $lag),
                    default                                            => min($lfVal, $ls[$succId] - $lag),
                };
            }

            $lf[$actId] = $lfVal;
            $ls[$actId] = $lfVal - $duration;
        }

        // ── Persist float_days and collect critical path ───────────────────────
        $criticalPath = [];

        foreach ($activities as $actId => $act) {
            $floatDays = $ls[$actId] - $es[$actId];
            $act->float_days = $floatDays;
            $act->save();

            if ($floatDays === 0) {
                $criticalPath[] = $actId;
            }
        }

        // Attach numeric offsets as extra attributes for API consumers.
        $activitiesOut = $activities->values()->map(function (NetworkActivity $act) use ($es, $ef, $ls, $lf): array {
            return array_merge($act->toArray(), [
                'es_offset' => $es[$act->id],
                'ef_offset' => $ef[$act->id],
                'ls_offset' => $ls[$act->id],
                'lf_offset' => $lf[$act->id],
            ]);
        });

        return [
            'activities'       => $activitiesOut,
            'critical_path'    => $criticalPath,
            'project_duration' => $projectEnd,
        ];
    }

    /**
     * Forecast project completion based on current progress on critical-path activities.
     */
    public function forecastCompletion(int $projectId): array
    {
        $result = $this->calculate($projectId);

        $criticalIds = $result['critical_path'];

        $cp = collect($result['activities'])
            ->filter(fn (array $a) => in_array($a['id'], $criticalIds, true));

        $completedWork = $cp->sum('actual_work');
        $totalPlanned  = $cp->sum('planned_work');
        $remainingWork = $cp->sum(fn (array $a) => max(0.0, (float) ($a['planned_work'] ?? 0) - (float) ($a['actual_work'] ?? 0)));

        $percentComplete = $totalPlanned > 0
            ? round((float) $completedWork / (float) $totalPlanned * 100, 1)
            : 0.0;

        return [
            'critical_path_activities'  => $cp->count(),
            'total_planned_work_hours'  => $totalPlanned,
            'completed_work_hours'      => $completedWork,
            'remaining_work_hours'      => $remainingWork,
            'percent_complete'          => $percentComplete,
            'project_duration_units'    => $result['project_duration'],
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build successor adjacency: activityId => list of relationship rows keyed by successor.
     *
     * @param  Collection<int|string, NetworkActivity>           $activities
     * @param  Collection<int, NetworkActivityRelationship>      $relationships
     * @return array<int|string, list<array<string, mixed>>>
     */
    private function buildSuccessorMap(Collection $activities, Collection $relationships): array
    {
        $map = [];
        foreach ($activities->keys() as $id) {
            $map[$id] = [];
        }

        foreach ($relationships as $rel) {
            if (isset($map[$rel->predecessor_activity_id])) {
                $map[$rel->predecessor_activity_id][] = [
                    'successor_activity_id' => $rel->successor_activity_id,
                    'relationship_type'     => $rel->relationship_type,
                    'lag_days'              => $rel->lag_days ?? 0,
                ];
            }
        }

        return $map;
    }

    /**
     * Build predecessor adjacency: activityId => list of relationship rows keyed by predecessor.
     *
     * @param  Collection<int|string, NetworkActivity>           $activities
     * @param  Collection<int, NetworkActivityRelationship>      $relationships
     * @return array<int|string, list<array<string, mixed>>>
     */
    private function buildPredecessorMap(Collection $activities, Collection $relationships): array
    {
        $map = [];
        foreach ($activities->keys() as $id) {
            $map[$id] = [];
        }

        foreach ($relationships as $rel) {
            if (isset($map[$rel->successor_activity_id])) {
                $map[$rel->successor_activity_id][] = [
                    'predecessor_activity_id' => $rel->predecessor_activity_id,
                    'relationship_type'       => $rel->relationship_type,
                    'lag_days'                => $rel->lag_days ?? 0,
                ];
            }
        }

        return $map;
    }

    /**
     * Kahn's algorithm topological sort.
     *
     * @param  list<int|string>                                  $nodeIds
     * @param  array<int|string, list<array<string, mixed>>>     $successors
     * @return list<int|string>
     */
    private function topologicalSort(array $nodeIds, array $successors): array
    {
        $inDegree = array_fill_keys($nodeIds, 0);

        foreach ($successors as $relList) {
            foreach ($relList as $rel) {
                $sucId = $rel['successor_activity_id'];
                if (array_key_exists($sucId, $inDegree)) {
                    $inDegree[$sucId]++;
                }
            }
        }

        $queue  = array_keys(array_filter($inDegree, static fn (int $d): bool => $d === 0));
        $sorted = [];

        while ($queue !== []) {
            $current  = array_shift($queue);
            $sorted[] = $current;

            foreach ($successors[$current] ?? [] as $rel) {
                $sucId = $rel['successor_activity_id'];
                if (!array_key_exists($sucId, $inDegree)) {
                    continue;
                }

                $inDegree[$sucId]--;
                if ($inDegree[$sucId] === 0) {
                    $queue[] = $sucId;
                }
            }
        }

        return $sorted;
    }
}
