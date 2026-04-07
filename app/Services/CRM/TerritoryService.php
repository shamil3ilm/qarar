<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Territory;
use App\Models\CRM\TerritoryAssignment;
use App\Models\CRM\TerritoryRoutingRule;
use App\Models\HR\Employee;
use Illuminate\Support\Facades\DB;

class TerritoryService
{
    /**
     * Create a new territory.
     */
    public function createTerritory(array $data, int $userId): Territory
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;
            return Territory::create($data);
        });
    }

    /**
     * Assign an employee to a territory with a given role.
     */
    public function assignEmployee(
        Territory $territory,
        int $employeeId,
        string $role,
        string $effectiveFrom,
        int $userId
    ): TerritoryAssignment {
        return DB::transaction(function () use ($territory, $employeeId, $role, $effectiveFrom, $userId) {
            return TerritoryAssignment::create([
                'organization_id' => $territory->organization_id,
                'territory_id'    => $territory->id,
                'employee_id'     => $employeeId,
                'role'            => $role,
                'effective_from'  => $effectiveFrom,
                'effective_to'    => null,
                'created_by'      => $userId,
            ]);
        });
    }

    /**
     * Remove (expire) a territory assignment immediately.
     */
    public function removeAssignment(TerritoryAssignment $assignment, int $userId): void
    {
        DB::transaction(function () use ($assignment) {
            $assignment->effective_to = now()->subDay()->toDateString();
            $assignment->save();
        });
    }

    /**
     * Create a territory routing rule.
     */
    public function createRoutingRule(array $data, int $userId): TerritoryRoutingRule
    {
        return DB::transaction(function () use ($data, $userId) {
            return TerritoryRoutingRule::create(array_merge($data, ['created_by' => $userId]));
        });
    }

    /**
     * Match an entity (lead/contact/opportunity) against routing rules
     * and return the first matching territory.
     */
    public function routeEntity(string $entityType, array $entityData): ?Territory
    {
        // Always scope to the authenticated user's organisation — never trust
        // caller-supplied organization_id for security.
        $orgId = auth()->user()->organization_id;

        $rulesQuery = TerritoryRoutingRule::active()
            ->forEntityType($entityType)
            ->byPriority()
            ->where('organization_id', $orgId)
            ->with('territory');

        $rules = $rulesQuery->get();

        foreach ($rules as $rule) {
            if ($rule->matchesEntity($entityData) && $rule->territory?->isActive()) {
                return $rule->territory;
            }
        }

        return null;
    }

    /**
     * Auto-assign a lead to a territory based on routing rules,
     * then assign the territory owner as the lead's handler.
     */
    public function autoAssignLead(Lead $lead, int $userId): ?TerritoryAssignment
    {
        $entityData = [
            'organization_id' => $lead->organization_id,
            'country_code'    => $lead->country_code,
            'state'           => $lead->state,
            'postal_code'     => $lead->postal_code,
            'city'            => $lead->city,
        ];

        $territory = $this->routeEntity(TerritoryRoutingRule::ENTITY_LEAD, $entityData);

        if ($territory === null) {
            return null;
        }

        return DB::transaction(function () use ($territory, $lead, $userId) {
            // Re-fetch with a row lock to prevent TOCTOU race conditions
            $territory = Territory::lockForUpdate()->findOrFail($territory->id);
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);

            $owner = $territory->getOwner();

            if ($owner === null) {
                return null;
            }

            // Update lead assignment
            $lead->assigned_to = $owner->user_id;
            $lead->save();

            // Return the territory assignment record for the owner
            return TerritoryAssignment::where('organization_id', $territory->organization_id)
                ->where('territory_id', $territory->id)
                ->where('employee_id', $owner->id)
                ->where('role', TerritoryAssignment::ROLE_OWNER)
                ->active()
                ->latest('effective_from')
                ->first();
        });
    }

    /**
     * Aggregate performance data for a territory over a date range.
     */
    public function getTerritoryPerformance(Territory $territory, string $from, string $to): array
    {
        $orgId = $territory->organization_id;

        // Find employees assigned to this territory during the period
        $employeeIds = TerritoryAssignment::where('territory_id', $territory->id)
            ->where('effective_from', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            })
            ->pluck('employee_id');

        // Map employees → users
        $userIds = Employee::whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        // Leads created by assigned employees in the period
        $leads = Lead::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        // Opportunities
        $opportunities = Opportunity::where('organization_id', $orgId)
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get();

        $wonDeals        = $opportunities->where('status', Opportunity::STATUS_WON);
        $pipelineValue   = (string) ($opportunities->where('status', Opportunity::STATUS_OPEN)->sum('amount') ?? 0);
        $wonValue        = (string) ($wonDeals->sum('amount') ?? 0);

        return [
            'territory'      => [
                'id'   => $territory->id,
                'name' => $territory->name,
                'code' => $territory->code,
            ],
            'period'         => ['from' => $from, 'to' => $to],
            'leads'          => [
                'total'     => $leads->count(),
                'converted' => $leads->where('status', Lead::STATUS_CONVERTED)->count(),
                'lost'      => $leads->where('status', Lead::STATUS_LOST)->count(),
            ],
            'opportunities'  => [
                'total'          => $opportunities->count(),
                'won'            => $wonDeals->count(),
                'won_value'      => $wonValue,
                'pipeline_value' => $pipelineValue,
            ],
            'assigned_employees' => $employeeIds->count(),
        ];
    }

    /**
     * Return per-salesperson territory and pipeline workload for the organisation.
     */
    public function getTeamWorkload(int $orgId): array
    {
        $assignments = TerritoryAssignment::where('organization_id', $orgId)
            ->active()
            ->with(['employee', 'territory'])
            ->get()
            ->groupBy('employee_id');

        $result = [];

        foreach ($assignments as $employeeId => $employeeAssignments) {
            $employee = $employeeAssignments->first()->employee;

            if ($employee === null || $employee->user_id === null) {
                continue;
            }

            $userId      = $employee->user_id;
            $territories = $employeeAssignments->pluck('territory')->filter();

            $openLeads = Lead::where('organization_id', $orgId)
                ->where('assigned_to', $userId)
                ->open()
                ->count();

            $openOpportunities = Opportunity::where('organization_id', $orgId)
                ->where('assigned_to', $userId)
                ->where('status', Opportunity::STATUS_OPEN)
                ->get();

            $wonOpportunities = Opportunity::where('organization_id', $orgId)
                ->where('assigned_to', $userId)
                ->where('status', Opportunity::STATUS_WON)
                ->sum('amount');

            $totalPipeline = Opportunity::where('organization_id', $orgId)
                ->where('assigned_to', $userId)
                ->sum('amount');

            $quotaAttainment = bccomp((string) $totalPipeline, '0', 4) > 0
                ? bcmul(bcdiv((string) $wonOpportunities, (string) $totalPipeline, 6), '100', 4)
                : '0.0000';

            $result[] = [
                'employee_id'        => $employeeId,
                'employee_name'      => trim($employee->first_name . ' ' . $employee->last_name),
                'territories'        => $territories->map(fn($t) => [
                    'id'   => $t->id,
                    'name' => $t->name,
                    'code' => $t->code,
                ])->values(),
                'open_leads'         => $openLeads,
                'open_opportunities' => $openOpportunities->count(),
                'pipeline_value'     => (float) $openOpportunities->sum('amount'),
                'quota_attainment'   => $quotaAttainment,
            ];
        }

        // Sort by most open leads descending
        usort($result, fn($a, $b) => $b['open_leads'] <=> $a['open_leads']);

        return $result;
    }
}
