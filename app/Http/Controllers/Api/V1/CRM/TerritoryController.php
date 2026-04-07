<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Lead;
use App\Models\CRM\Territory;
use App\Models\CRM\TerritoryAssignment;
use App\Models\CRM\TerritoryRoutingRule;
use App\Services\CRM\TerritoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    public function __construct(
        private TerritoryService $territoryService
    ) {}

    // -------------------------------------------------------------------------
    // Territories CRUD
    // -------------------------------------------------------------------------

    /**
     * List territories for the authenticated organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Territory::with(['parent', 'creator'])
            ->latest()
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('territory_type'), fn($q) => $q->ofType($request->input('territory_type')))
            ->when($request->has('parent_id'), fn($q) => $q->where('parent_id', $request->integer('parent_id')))
            ->when($request->boolean('roots_only'), fn($q) => $q->roots())
            ->when($request->has('country_code'), fn($q) => $q->forCountry($request->input('country_code')));

        $territories = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($territories);
    }

    /**
     * Create a territory.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id'       => 'nullable|integer|exists:territories,id',
            'name'            => 'required|string|max:200',
            'code'            => 'required|string|max:50',
            'description'     => 'nullable|string',
            'territory_type'  => 'sometimes|in:global,region,country,state,city,postal_zone,custom',
            'country_code'    => 'nullable|string|max:3',
            'state_code'      => 'nullable|string|max:10',
            'postal_codes'    => 'nullable|array',
            'postal_codes.*'  => 'string|max:20',
            'status'          => 'sometimes|in:active,inactive',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $territory = $this->territoryService->createTerritory($validated, $request->user()->id);

        return $this->created($territory->load('parent'));
    }

    /**
     * Show a territory.
     */
    public function show(int $id): JsonResponse
    {
        $territory = Territory::with(['parent', 'children', 'assignments.employee', 'routingRules', 'creator'])
            ->findOrFail($id);

        return $this->success($territory);
    }

    /**
     * Update a territory.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);

        $validated = $request->validate([
            'parent_id'       => 'nullable|integer|exists:territories,id',
            'name'            => 'sometimes|string|max:200',
            'code'            => 'sometimes|string|max:50',
            'description'     => 'nullable|string',
            'territory_type'  => 'sometimes|in:global,region,country,state,city,postal_zone,custom',
            'country_code'    => 'nullable|string|max:3',
            'state_code'      => 'nullable|string|max:10',
            'postal_codes'    => 'nullable|array',
            'postal_codes.*'  => 'string|max:20',
            'status'          => 'sometimes|in:active,inactive',
        ]);

        $territory->update($validated);

        return $this->success($territory->refresh()->load('parent'));
    }

    /**
     * Soft-delete a territory.
     */
    public function destroy(int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);
        $territory->delete();

        return $this->success([], 'Territory deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    /**
     * List assignments for a territory.
     */
    public function assignmentIndex(Request $request, int $territoryId): JsonResponse
    {
        $territory = Territory::findOrFail($territoryId);

        $query = $territory->assignments()->with('employee')->latest()
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $assignments = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($assignments);
    }

    /**
     * Add an assignment to a territory.
     */
    public function assignmentStore(Request $request, int $territoryId): JsonResponse
    {
        $territory = Territory::findOrFail($territoryId);

        $validated = $request->validate([
            'employee_id'    => 'required|integer|exists:hr_employees,id',
            'role'           => 'sometimes|in:owner,backup,viewer',
            'effective_from' => 'required|date',
            'effective_to'   => 'nullable|date|after:effective_from',
        ]);

        $assignment = $this->territoryService->assignEmployee(
            territory:     $territory,
            employeeId:    $validated['employee_id'],
            role:          $validated['role'] ?? TerritoryAssignment::ROLE_OWNER,
            effectiveFrom: $validated['effective_from'],
            userId:        $request->user()->id,
        );

        if (!empty($validated['effective_to'])) {
            $assignment->effective_to = $validated['effective_to'];
            $assignment->save();
        }

        return $this->created($assignment->load('employee'));
    }

    /**
     * Remove an assignment (expire it as of yesterday).
     */
    public function assignmentDestroy(Request $request, int $assignmentId): JsonResponse
    {
        $assignment = TerritoryAssignment::findOrFail($assignmentId);
        $this->territoryService->removeAssignment($assignment, $request->user()->id);

        return $this->success([], 'Assignment removed successfully.');
    }

    // -------------------------------------------------------------------------
    // Routing Rules
    // -------------------------------------------------------------------------

    /**
     * List routing rules.
     */
    public function routingRuleIndex(Request $request): JsonResponse
    {
        $query = TerritoryRoutingRule::with('territory')
            ->orderBy('priority')
            ->when($request->has('entity_type'), fn($q) => $q->forEntityType($request->input('entity_type')))
            ->when($request->has('territory_id'), fn($q) => $q->where('territory_id', $request->integer('territory_id')))
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $rules = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($rules);
    }

    /**
     * Create a routing rule.
     */
    public function routingRuleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'territory_id' => 'required|integer|exists:territories,id',
            'entity_type'  => 'sometimes|in:lead,opportunity,contact',
            'match_field'  => 'required|in:country,state,postal_code,city,custom',
            'match_value'  => 'required|string|max:200',
            'priority'     => 'sometimes|integer|min:1|max:255',
            'is_active'    => 'sometimes|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $rule = $this->territoryService->createRoutingRule($validated, $request->user()->id);

        return $this->created($rule->load('territory'));
    }

    /**
     * Update a routing rule.
     */
    public function routingRuleUpdate(Request $request, int $id): JsonResponse
    {
        $rule = TerritoryRoutingRule::findOrFail($id);

        $validated = $request->validate([
            'territory_id' => 'sometimes|integer|exists:territories,id',
            'entity_type'  => 'sometimes|in:lead,opportunity,contact',
            'match_field'  => 'sometimes|in:country,state,postal_code,city,custom',
            'match_value'  => 'sometimes|string|max:200',
            'priority'     => 'sometimes|integer|min:1|max:255',
            'is_active'    => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return $this->success($rule->load('territory'));
    }

    /**
     * Delete a routing rule.
     */
    public function routingRuleDestroy(int $id): JsonResponse
    {
        $rule = TerritoryRoutingRule::findOrFail($id);
        $rule->delete();

        return $this->success([], 'Routing rule deleted successfully.');
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Auto-assign a lead to a territory owner based on routing rules.
     */
    public function autoAssignLead(Request $request, int $leadId): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);
        $assignment = $this->territoryService->autoAssignLead($lead, $request->user()->id);

        if ($assignment === null) {
            return $this->success(null, 'No matching territory or owner found for this lead.');
        }

        return $this->success($assignment->load(['territory', 'employee']), 'Lead auto-assigned successfully.');
    }

    /**
     * Get performance metrics for a territory over a date range.
     */
    public function performance(Request $request, int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);

        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $performance = $this->territoryService->getTerritoryPerformance(
            $territory,
            $validated['from'],
            $validated['to'],
        );

        return $this->success($performance);
    }

    /**
     * Get per-salesperson workload summary for the organisation.
     */
    public function teamWorkload(Request $request): JsonResponse
    {
        $workload = $this->territoryService->getTeamWorkload($this->organizationId($request));

        return $this->success($workload);
    }
}
