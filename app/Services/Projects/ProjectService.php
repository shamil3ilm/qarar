<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\ProjectCostEntry;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\ProjectMilestone;
use App\Models\Projects\ProjectTimeEntry;
use App\Models\Projects\WbsElement;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
    ) {}

    // ── Projects ──────────────────────────────────────────────────────────────

    /**
     * Create a new project and auto-generate its project number.
     */
    public function createProject(array $data, int $userId): Project
    {
        return DB::transaction(function () use ($data, $userId): Project {
            $projectNumber = $this->numberGenerator->generate('PRJ');

            $project = Project::create([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $data['branch_id'] ?? null,
                'project_number' => $projectNumber,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'project_type' => $data['project_type'] ?? Project::TYPE_INTERNAL,
                'customer_id' => $data['customer_id'] ?? null,
                'status' => Project::STATUS_DRAFT,
                'priority' => $data['priority'] ?? Project::PRIORITY_MEDIUM,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'budget' => $data['budget'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'manager_id' => $data['manager_id'] ?? null,
                'created_by' => $userId,
            ]);

            return $project->fresh();
        });
    }

    /**
     * Update an existing project.
     */
    public function updateProject(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh();
    }

    /**
     * Transition a project to active status.
     */
    public function activateProject(Project $project, int $userId): Project
    {
        if (!$project->canBeActivated()) {
            throw new \InvalidArgumentException(
                "Project cannot be activated from status '{$project->status}'."
            );
        }

        return DB::transaction(function () use ($project, $userId): Project {
            return $project->activate($userId);
        });
    }

    /**
     * Transition a project to completed status.
     */
    public function completeProject(Project $project, int $userId): Project
    {
        if (!$project->canBeCompleted()) {
            throw new \InvalidArgumentException(
                "Project cannot be completed from status '{$project->status}'."
            );
        }

        return DB::transaction(function () use ($project, $userId): Project {
            // Mark any pending milestones that are past their due date as missed
            $project->milestones()
                ->where('status', ProjectMilestone::STATUS_PENDING)
                ->where('due_date', '<', now()->toDateString())
                ->update(['status' => ProjectMilestone::STATUS_MISSED]);

            return $project->complete($userId);
        });
    }

    // ── WBS Elements ─────────────────────────────────────────────────────────

    /**
     * Create a WBS element under a project (optionally under a parent element).
     */
    public function createWbsElement(Project $project, array $data, int $userId): WbsElement
    {
        return DB::transaction(function () use ($project, $data): WbsElement {
            $parentId = $data['parent_id'] ?? null;
            $sortOrder = $data['sort_order']
                ?? WbsElement::where('project_id', $project->id)
                    ->where('parent_id', $parentId)
                    ->max('sort_order') + 1;

            $element = WbsElement::create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'parent_id' => $parentId,
                'wbs_code' => $data['wbs_code'] ?? WbsElement::getNextCode($project, $parentId),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? WbsElement::STATUS_CREATED,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'planned_cost' => $data['planned_cost'] ?? 0,
                'planned_revenue' => $data['planned_revenue'] ?? 0,
                'responsible_employee_id' => $data['responsible_employee_id'] ?? null,
                'progress_percent' => 0,
                'sort_order' => $sortOrder,
            ]);

            return $element->fresh();
        });
    }

    /**
     * Update WBS element progress and recalculate parent element progress.
     */
    public function updateProgress(WbsElement $element, int $percent, int $userId): WbsElement
    {
        if ($percent < 0 || $percent > 100) {
            throw new \InvalidArgumentException('Progress percent must be between 0 and 100.');
        }

        return DB::transaction(function () use ($element, $percent): WbsElement {
            $element->update(['progress_percent' => $percent]);

            // Propagate up the hierarchy
            $this->recalculateParentProgress($element);

            return $element->fresh();
        });
    }

    /**
     * Walk up the WBS tree and recalculate weighted-average progress for each ancestor.
     */
    private function recalculateParentProgress(WbsElement $element): void
    {
        if ($element->parent_id === null) {
            return;
        }

        $parent = $element->parent()->with('children')->first();

        if ($parent === null) {
            return;
        }

        $children = $parent->children;
        $avgProgress = $children->isEmpty()
            ? 0
            : (int) round($children->avg('progress_percent'));

        $parent->update(['progress_percent' => $avgProgress]);

        // Recurse further up
        $this->recalculateParentProgress($parent);
    }

    // ── Milestones ────────────────────────────────────────────────────────────

    /**
     * Create a project milestone.
     */
    public function createMilestone(array $data, int $userId): ProjectMilestone
    {
        return DB::transaction(function () use ($data, $userId): ProjectMilestone {
            $milestone = ProjectMilestone::create([
                'organization_id' => auth()->user()->organization_id,
                'project_id' => $data['project_id'],
                'wbs_element_id' => $data['wbs_element_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'],
                'status' => ProjectMilestone::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $milestone->fresh();
        });
    }

    /**
     * Mark a milestone as achieved.
     */
    public function achieveMilestone(ProjectMilestone $milestone, int $userId): ProjectMilestone
    {
        if ($milestone->isAchieved()) {
            throw new \InvalidArgumentException('Milestone is already achieved.');
        }

        return DB::transaction(function () use ($milestone, $userId): ProjectMilestone {
            return $milestone->achieve($userId);
        });
    }

    // ── Time Entries ──────────────────────────────────────────────────────────

    /**
     * Log time for an employee on a project/WBS element.
     * Also creates a corresponding labor cost entry.
     */
    public function logTime(array $data, int $userId): ProjectTimeEntry
    {
        return DB::transaction(function () use ($data, $userId): ProjectTimeEntry {
            $organizationId = auth()->user()->organization_id;

            $entry = ProjectTimeEntry::create([
                'organization_id' => $organizationId,
                'project_id' => $data['project_id'],
                'wbs_element_id' => $data['wbs_element_id'] ?? null,
                'employee_id' => $data['employee_id'],
                'work_date' => $data['work_date'],
                'hours' => $data['hours'],
                'description' => $data['description'] ?? null,
                'is_billable' => $data['is_billable'] ?? false,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'created_by' => $userId,
            ]);

            // Auto-create a labor cost entry
            $hourlyRate = (float) ($data['hourly_rate'] ?? 0);
            $laborAmount = (float) $data['hours'] * $hourlyRate;

            if ($laborAmount > 0) {
                $this->createCostEntry([
                    'organization_id' => $organizationId,
                    'project_id' => $data['project_id'],
                    'wbs_element_id' => $data['wbs_element_id'] ?? null,
                    'cost_type' => ProjectCostEntry::TYPE_LABOR,
                    'description' => $data['description'] ?? "Labor: {$data['hours']}h",
                    'amount' => $laborAmount,
                    'currency_code' => $data['currency_code'] ?? 'SAR',
                    'cost_date' => $data['work_date'],
                    'reference_type' => ProjectTimeEntry::class,
                    'reference_id' => $entry->id,
                ], $userId);
            }

            return $entry->fresh(['employee', 'wbsElement']);
        });
    }

    /**
     * Approve a time entry.
     */
    public function approveTime(ProjectTimeEntry $entry, int $userId): ProjectTimeEntry
    {
        if ($entry->isApproved()) {
            throw new \InvalidArgumentException('Time entry is already approved.');
        }

        $entry->update([
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $entry->fresh();
    }

    // ── Cost Entries ──────────────────────────────────────────────────────────

    /**
     * Add a cost entry to a project/WBS element.
     * Updates WbsElement.actual_cost if a WBS element is specified.
     */
    public function addCostEntry(array $data, int $userId): ProjectCostEntry
    {
        return DB::transaction(function () use ($data, $userId): ProjectCostEntry {
            $entry = $this->createCostEntry($data, $userId);

            return $entry->fresh(['wbsElement']);
        });
    }

    /**
     * Internal helper: persist a cost entry and update WBS actual_cost.
     */
    private function createCostEntry(array $data, int $userId): ProjectCostEntry
    {
        $organizationId = $data['organization_id'] ?? auth()->user()->organization_id;

        $entry = ProjectCostEntry::create([
            'organization_id' => $organizationId,
            'project_id' => $data['project_id'],
            'wbs_element_id' => $data['wbs_element_id'] ?? null,
            'cost_type' => $data['cost_type'] ?? ProjectCostEntry::TYPE_OTHER,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'] ?? 'SAR',
            'cost_date' => $data['cost_date'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'journal_entry_id' => $data['journal_entry_id'] ?? null,
            'created_by' => $userId,
        ]);

        // Update WBS element actual_cost
        if (!empty($data['wbs_element_id'])) {
            $element = WbsElement::find($data['wbs_element_id']);

            if ($element !== null) {
                $totalCost = ProjectCostEntry::where('wbs_element_id', $element->id)->sum('amount');
                $element->update(['actual_cost' => $totalCost]);
            }
        }

        return $entry;
    }

    // ── Members ───────────────────────────────────────────────────────────────

    /**
     * Add an employee as a project member.
     */
    public function addMember(Project $project, int $employeeId, string $role, int $userId): ProjectMember
    {
        return DB::transaction(function () use ($project, $employeeId, $role): ProjectMember {
            $existing = ProjectMember::where('project_id', $project->id)
                ->where('employee_id', $employeeId)
                ->first();

            if ($existing !== null) {
                throw new \InvalidArgumentException('Employee is already a member of this project.');
            }

            $member = ProjectMember::create([
                'project_id' => $project->id,
                'employee_id' => $employeeId,
                'role' => $role,
                'joined_at' => now()->toDateString(),
            ]);

            return $member->fresh(['employee']);
        });
    }

    /**
     * Remove a project member.
     */
    public function removeMember(ProjectMember $member): void
    {
        $member->delete();
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * Assemble a summary dashboard for a project.
     */
    public function getProjectDashboard(Project $project): array
    {
        $project->loadMissing([
            'allWbsElements',
            'milestones',
            'costEntries',
            'timeEntries',
            'members.employee',
        ]);

        $totalBudget = (float) $project->budget;
        $actualCost = (float) $project->costEntries->sum('amount');
        $budgetVariance = $totalBudget - $actualCost;

        $completionPercent = $project->getCompletionPercent();

        $overdueMilestones = $project->milestones
            ->filter(fn (ProjectMilestone $m) => $m->isOverdue())
            ->values();

        $pendingApprovals = $project->timeEntries
            ->filter(fn (ProjectTimeEntry $t) => !$t->isApproved())
            ->count();

        $costByType = $project->costEntries
            ->groupBy('cost_type')
            ->map(fn ($entries) => [
                'count' => $entries->count(),
                'total' => round((float) $entries->sum('amount'), 2),
            ]);

        $totalHours = (float) $project->timeEntries->sum('hours');

        $milestoneStats = [
            'total' => $project->milestones->count(),
            'achieved' => $project->milestones->where('status', ProjectMilestone::STATUS_ACHIEVED)->count(),
            'pending' => $project->milestones->where('status', ProjectMilestone::STATUS_PENDING)->count(),
            'missed' => $project->milestones->where('status', ProjectMilestone::STATUS_MISSED)->count(),
            'overdue' => $overdueMilestones->count(),
        ];

        return [
            'project_id' => $project->id,
            'project_number' => $project->project_number,
            'name' => $project->name,
            'status' => $project->status,
            'priority' => $project->priority,
            'budget' => $totalBudget,
            'actual_cost' => $actualCost,
            'budget_variance' => $budgetVariance,
            'budget_utilization_percent' => $totalBudget > 0
                ? round(($actualCost / $totalBudget) * 100, 2)
                : 0,
            'completion_percent' => $completionPercent,
            'total_hours_logged' => $totalHours,
            'pending_approvals' => $pendingApprovals,
            'milestones' => $milestoneStats,
            'overdue_milestones' => $overdueMilestones->map(fn (ProjectMilestone $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'due_date' => $m->due_date->toDateString(),
                'days_overdue' => (int) $m->due_date->diffInDays(now()),
            ])->values(),
            'cost_by_type' => $costByType,
            'team_size' => $project->members->count(),
        ];
    }
}
