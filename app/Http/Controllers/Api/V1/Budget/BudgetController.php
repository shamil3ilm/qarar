<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetCommitment;
use App\Models\Budget\BudgetLine;
use App\Services\Accounting\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService,
    ) {}

    // ----------------------------------------------------------------
    // Budgets CRUD
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets
     */
    public function index(Request $request): JsonResponse
    {
        $query = Budget::with(['fiscalYear:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('budget_type'), fn($q) => $q->where('budget_type', $request->budget_type))
            ->when($request->filled('fiscal_year_id'), fn($q) => $q->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', "%{$request->search}%"));

        $budgets = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($budgets);
    }

    /**
     * POST /budget/budgets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id'         => ['nullable', 'exists:fiscal_years,id'],
            'name'                   => ['required', 'string', 'max:255'],
            'budget_type'            => ['nullable', 'string', 'in:annual,quarterly,project,department'],
            'period_start'           => ['required', 'date'],
            'period_end'             => ['required', 'date', 'after_or_equal:period_start'],
            'currency_code'          => ['nullable', 'string', 'size:3'],
            'description'            => ['nullable', 'string'],
            'lines'                  => ['nullable', 'array'],
            'lines.*.name'           => ['required_with:lines', 'string', 'max:255'],
            'lines.*.account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines.*.department_id'  => ['nullable', 'exists:departments,id'],
            'lines.*.q1_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q2_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q3_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.q4_amount'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $budget = $this->budgetService->createBudget($validated, $validated['lines'] ?? [], auth()->id());

        return $this->created($budget->load(['lines', 'fiscalYear']));
    }

    /**
     * GET /budget/budgets/{id}
     */
    public function show(int $id): JsonResponse
    {
        $budget = Budget::with([
            'fiscalYear:id,name',
            'lines.account:id,code,name',
            'lines.costCenter:id,code,name',
            'lines.department:id,name',
            'revisions.creator:id,name',
            'approver:id,name',
            'creator:id,name',
        ])->find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        return $this->success($budget);
    }

    /**
     * PUT /budget/budgets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
            'name'           => ['sometimes', 'string', 'max:255'],
            'budget_type'    => ['sometimes', 'string', 'in:annual,quarterly,project,department'],
            'period_start'   => ['sometimes', 'date'],
            'period_end'     => ['sometimes', 'date', 'after_or_equal:period_start'],
            'currency_code'  => ['sometimes', 'string', 'size:3'],
            'description'    => ['nullable', 'string'],
        ]);

        try {
            if (!$budget->isEditable()) {
                throw new InvalidArgumentException('Only draft or submitted budgets can be edited.');
            }

            $budget->update($validated);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'BUDGET_NOT_EDITABLE', 422);
        }

        return $this->success($budget->fresh(['lines', 'fiscalYear']));
    }

    /**
     * DELETE /budget/budgets/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        if (!$budget->isEditable()) {
            return $this->error('Only draft or submitted budgets can be deleted.', 'BUDGET_NOT_DELETABLE', 422);
        }

        $budget->delete();

        return $this->success(null, 'Budget deleted successfully.');
    }

    // ----------------------------------------------------------------
    // Lifecycle transitions
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/submit
     */
    public function submit(int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        try {
            $budget = $this->budgetService->submitBudget($budget, auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_TRANSITION', 422);
        }

        return $this->success($budget, 'Budget submitted for approval.');
    }

    /**
     * POST /budget/budgets/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        try {
            $budget = $this->budgetService->approveBudget($budget, auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_TRANSITION', 422);
        }

        return $this->success($budget, 'Budget approved.');
    }

    /**
     * POST /budget/budgets/{id}/activate
     */
    public function activate(int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        try {
            $budget = $this->budgetService->activateBudget($budget, auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_TRANSITION', 422);
        }

        return $this->success($budget, 'Budget activated.');
    }

    // ----------------------------------------------------------------
    // Lines
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/lines
     */
    public function storeLine(Request $request, int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'department_id'  => ['nullable', 'exists:departments,id'],
            'q1_amount'      => ['nullable', 'numeric', 'min:0'],
            'q2_amount'      => ['nullable', 'numeric', 'min:0'],
            'q3_amount'      => ['nullable', 'numeric', 'min:0'],
            'q4_amount'      => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if (!$budget->isEditable()) {
                throw new InvalidArgumentException('Only draft or submitted budgets can be edited.');
            }

            $q1 = (string) ($validated['q1_amount'] ?? 0);
            $q2 = (string) ($validated['q2_amount'] ?? 0);
            $q3 = (string) ($validated['q3_amount'] ?? 0);
            $q4 = (string) ($validated['q4_amount'] ?? 0);

            $line = $budget->lines()->create(array_merge($validated, [
                'total_amount'     => bcadd(bcadd(bcadd($q1, $q2, 4), $q3, 4), $q4, 4),
                'committed_amount' => 0,
                'actual_amount'    => 0,
            ]));

            $budget->update(['total_amount' => $budget->getTotalBudgeted()]);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'BUDGET_NOT_EDITABLE', 422);
        }

        return $this->created($line);
    }

    /**
     * PUT /budget/budgets/{budgetId}/lines/{lineId}
     */
    public function updateLine(Request $request, int $budgetId, int $lineId): JsonResponse
    {
        $budget = Budget::find($budgetId);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $line = BudgetLine::where('budget_id', $budgetId)->find($lineId);

        if ($line === null) {
            return $this->notFound('Budget line not found.');
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'department_id'  => ['nullable', 'exists:departments,id'],
            'q1_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q2_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q3_amount'      => ['sometimes', 'numeric', 'min:0'],
            'q4_amount'      => ['sometimes', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $line = $this->budgetService->updateLine($line, $validated, auth()->id());

        return $this->success($line->load(['account', 'costCenter', 'department']));
    }

    /**
     * DELETE /budget/budgets/{budgetId}/lines/{lineId}
     */
    public function destroyLine(int $budgetId, int $lineId): JsonResponse
    {
        $budget = Budget::find($budgetId);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $line = BudgetLine::where('budget_id', $budgetId)->find($lineId);

        if ($line === null) {
            return $this->notFound('Budget line not found.');
        }

        try {
            if (!$budget->isEditable()) {
                throw new InvalidArgumentException('Only draft or submitted budgets can be edited.');
            }

            $line->delete();
            $budget->update(['total_amount' => $budget->getTotalBudgeted()]);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'BUDGET_NOT_EDITABLE', 422);
        }

        return $this->success(null, 'Budget line deleted successfully.');
    }

    // ----------------------------------------------------------------
    // Revisions
    // ----------------------------------------------------------------

    /**
     * POST /budget/budgets/{id}/revisions
     */
    public function storeRevision(Request $request, int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $validated = $request->validate([
            'reason'                         => ['required', 'string'],
            'line_changes'                   => ['required', 'array', 'min:1'],
            'line_changes.*.budget_line_id'  => ['required', 'exists:budget_lines,id'],
            'line_changes.*.field_changed'   => ['required', 'string', 'in:q1_amount,q2_amount,q3_amount,q4_amount,total_amount'],
            'line_changes.*.new_value'       => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $revision = $this->budgetService->reviseBudget(
                $budget,
                $validated['line_changes'],
                $validated['reason'],
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'REVISION_ERROR', 422);
        }

        return $this->created($revision->load(['lines', 'creator']));
    }

    // ----------------------------------------------------------------
    // Commitments
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets/{id}/commitments
     */
    public function commitments(int $id): JsonResponse
    {
        $budget = Budget::find($id);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $lineIds = $budget->lines()->pluck('id');

        $commitments = BudgetCommitment::with(['budgetLine', 'creator:id,name'])
            ->whereIn('budget_line_id', $lineIds)
            ->orderByDesc('committed_at')
            ->get();

        return $this->success($commitments);
    }

    // ----------------------------------------------------------------
    // Reports
    // ----------------------------------------------------------------

    /**
     * GET /budget/budgets/vs-actual
     */
    public function vsActual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_id' => ['required', 'exists:budgets,id'],
        ]);

        $budget = Budget::find((int) $validated['budget_id']);

        if ($budget === null) {
            return $this->notFound('Budget not found.');
        }

        $report = $this->budgetService->getBudgetVsActual($budget);

        return $this->success($report);
    }
}
