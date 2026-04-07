<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ConsolidationEntity;
use App\Models\Accounting\ConsolidationGroup;
use App\Models\Accounting\ConsolidationPeriod;
use App\Models\Accounting\EliminationEntry;
use App\Services\Accounting\ConsolidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidationController extends Controller
{
    public function __construct(
        private ConsolidationService $consolidationService
    ) {}

    // =========================================================================
    // Consolidation Groups
    // =========================================================================

    /**
     * List consolidation groups.
     */
    public function indexGroups(Request $request): JsonResponse
    {
        $groups = ConsolidationGroup::with(['entities.entityOrganization', 'createdBy:id,name'])
            ->withCount('periods')
            ->when($request->active === 'true', fn ($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($groups, null);
    }

    /**
     * Show a single consolidation group.
     */
    public function showGroup(int $id): JsonResponse
    {
        $group = ConsolidationGroup::with([
            'entities.entityOrganization',
            'periods',
            'createdBy:id,name',
        ])->find($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        return $this->success($group, 'Consolidation group retrieved.');
    }

    /**
     * Create a new consolidation group.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'description'                   => 'nullable|string',
            'currency_code'                 => 'required|string|size:3',
            'entities'                      => 'nullable|array',
            'entities.*.entity_organization_id' => 'required_with:entities|exists:organizations,id',
            'entities.*.name'               => 'required_with:entities|string|max:255',
            'entities.*.ownership_percent'  => 'nullable|numeric|min:0|max:100',
            'entities.*.consolidation_method' => 'nullable|in:full,proportional,equity',
            'entities.*.local_currency'     => 'nullable|string|size:3',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $entities = $validated['entities'] ?? [];
        unset($validated['entities']);

        $group = $this->consolidationService->createGroup($validated, $entities, auth()->id());

        return $this->created($group, 'Consolidation group created.');
    }

    /**
     * Update a consolidation group.
     */
    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $group = ConsolidationGroup::find($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'currency_code' => 'sometimes|string|size:3',
            'is_active'     => 'sometimes|boolean',
        ]);

        $group->update($validated);

        return $this->success($group->fresh(['entities', 'createdBy:id,name']), 'Consolidation group updated.');
    }

    /**
     * Delete a consolidation group.
     */
    public function destroyGroup(int $id): JsonResponse
    {
        $group = ConsolidationGroup::find($id);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        if ($group->periods()->where('status', ConsolidationPeriod::STATUS_COMPLETED)->exists()) {
            return $this->error('Cannot delete a group that has completed periods.', 422);
        }

        $group->delete();

        return $this->success(null, 'Consolidation group deleted.');
    }

    // =========================================================================
    // Consolidation Entities
    // =========================================================================

    /**
     * Add an entity to a consolidation group.
     */
    public function addEntity(Request $request, int $groupId): JsonResponse
    {
        $group = ConsolidationGroup::find($groupId);

        if (!$group) {
            return $this->notFound('Consolidation group not found.');
        }

        $validated = $request->validate([
            'entity_organization_id' => 'required|exists:organizations,id',
            'name'                   => 'required|string|max:255',
            'ownership_percent'      => 'nullable|numeric|min:0|max:100',
            'consolidation_method'   => 'nullable|in:full,proportional,equity',
            'local_currency'         => 'nullable|string|size:3',
        ]);

        $entity = $this->consolidationService->addEntity($group, $validated, auth()->id());

        return $this->created(
            $entity->load('entityOrganization'),
            'Entity added to consolidation group.'
        );
    }

    /**
     * Remove an entity from a consolidation group.
     */
    public function removeEntity(int $entityId): JsonResponse
    {
        $entity = ConsolidationEntity::find($entityId);

        if (!$entity) {
            return $this->notFound('Consolidation entity not found.');
        }

        $entity->delete();

        return $this->success(null, 'Entity removed from consolidation group.');
    }

    // =========================================================================
    // Consolidation Periods
    // =========================================================================

    /**
     * List consolidation periods.
     */
    public function indexPeriods(Request $request): JsonResponse
    {
        $periods = ConsolidationPeriod::with(['group:id,name,currency_code', 'createdBy:id,name'])
            ->withCount(['eliminationEntries', 'consolidatedBalances'])
            ->when($request->group_id, fn ($q, $id) => $q->where('consolidation_group_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('period_start')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($periods, null);
    }

    /**
     * Show a single consolidation period.
     */
    public function showPeriod(int $id): JsonResponse
    {
        $period = ConsolidationPeriod::with([
            'group.entities.entityOrganization',
            'fiscalYear',
            'createdBy:id,name',
        ])
        ->withCount(['eliminationEntries', 'consolidatedBalances'])
        ->find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        return $this->success($period, 'Consolidation period retrieved.');
    }

    /**
     * Create a consolidation period.
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consolidation_group_id' => 'required|exists:consolidation_groups,id',
            'fiscal_year_id'         => 'nullable|exists:fiscal_years,id',
            'period_start'           => 'required|date',
            'period_end'             => 'required|date|after_or_equal:period_start',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $period = $this->consolidationService->createPeriod($validated, auth()->id());

        return $this->created(
            $period->load('group:id,name,currency_code'),
            'Consolidation period created.'
        );
    }

    /**
     * Collect entity balances into the consolidation period.
     */
    public function collectBalances(Request $request, int $id): JsonResponse
    {
        $period = ConsolidationPeriod::find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot collect balances for a completed period.', 422);
        }

        $this->consolidationService->collectEntityBalances($period, auth()->id());

        return $this->success(
            [
                'period'             => $period->fresh(),
                'balances_collected' => $period->consolidatedBalances()->count(),
            ],
            'Entity balances collected successfully.'
        );
    }

    /**
     * Complete a consolidation period.
     */
    public function completePeriod(Request $request, int $id): JsonResponse
    {
        $period = ConsolidationPeriod::find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        return $this->tryAction(
            fn() => $this->consolidationService->completePeriod($period, auth()->id()),
            'Consolidation period completed.'
        );
    }

    /**
     * Get the consolidated financial report for a period.
     */
    public function report(int $id): JsonResponse
    {
        $period = ConsolidationPeriod::find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $report = $this->consolidationService->generateConsolidatedReport($period);

        return $this->success($report, 'Consolidated report generated.');
    }

    // =========================================================================
    // Elimination Entries
    // =========================================================================

    /**
     * List elimination entries for a period.
     */
    public function indexEliminations(Request $request, int $periodId): JsonResponse
    {
        $period = ConsolidationPeriod::find($periodId);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $entries = EliminationEntry::where('consolidation_period_id', $periodId)
            ->with([
                'debitAccount:id,code,name',
                'creditAccount:id,code,name',
                'createdBy:id,name',
            ])
            ->when($request->entry_type, fn ($q, $t) => $q->where('entry_type', $t))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($entries, null);
    }

    /**
     * Auto-generate IC elimination entries for a consolidation period.
     *
     * POST /consolidation/periods/{id}/generate-eliminations
     */
    public function generateEliminations(int $id): JsonResponse
    {
        $period = ConsolidationPeriod::find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot generate eliminations for a completed period.', 422);
        }

        try {
            $entries = $this->consolidationService->generateEliminationEntries($period, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'generated' => count($entries),
            'entries'   => $entries,
        ], 'Elimination entries generated.');
    }

    /**
     * List auto-generated elimination entries for a period.
     *
     * GET /consolidation/periods/{id}/eliminations-auto
     */
    public function eliminationsAuto(int $id): JsonResponse
    {
        $period = ConsolidationPeriod::find($id);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        $entries = $this->consolidationService->getEliminationEntries($period);

        return $this->success($entries->all(), 'Elimination entries retrieved.');
    }

    /**
     * Create an elimination entry for a consolidation period.
     */
    public function storeElimination(Request $request, int $periodId): JsonResponse
    {
        $period = ConsolidationPeriod::find($periodId);

        if (!$period) {
            return $this->notFound('Consolidation period not found.');
        }

        if ($period->isCompleted()) {
            return $this->error('Cannot add elimination entries to a completed period.', 422);
        }

        $validated = $request->validate([
            'entry_type'       => 'required|in:intercompany_receivable,intercompany_payable,dividend,investment,other',
            'description'      => 'required|string|max:500',
            'debit_account_id' => 'required|exists:chart_of_accounts,id',
            'credit_account_id' => 'required|exists:chart_of_accounts,id|different:debit_account_id',
            'amount'           => 'required|numeric|min:0.0001',
            'currency_code'    => 'nullable|string|size:3',
        ]);

        $validated['organization_id']         = $this->organizationId($request);
        $validated['consolidation_period_id'] = $periodId;

        $entry = $this->consolidationService->createEliminationEntry($validated, auth()->id());

        return $this->created(
            $entry->load(['debitAccount:id,code,name', 'creditAccount:id,code,name']),
            'Elimination entry created.'
        );
    }
}
