<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\BudgetTransfer;
use App\Services\Budget\BudgetTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Budget Transfer Controller — SAP FM budget transfer (T-code FM2S).
 *
 * POST   /api/v1/budget/transfers            store
 * GET    /api/v1/budget/transfers            index
 * GET    /api/v1/budget/transfers/{id}       show
 * POST   /api/v1/budget/transfers/{id}/submit   → submitted
 * POST   /api/v1/budget/transfers/{id}/review    → approved + posted | rejected
 */
class BudgetTransferController extends Controller
{
    public function __construct(
        private readonly BudgetTransferService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transfers = $this->service->getForOrganization(
            $request->user()->organization_id,
            $request->only(['status', 'from_budget_id', 'to_budget_id']),
        );

        return $this->successResponse($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_budget_line_id' => 'required|integer|exists:budget_lines,id',
            'to_budget_line_id'   => 'required|integer|exists:budget_lines,id|different:from_budget_line_id',
            'amount'              => 'required|numeric|min:0.01',
            'reason'              => 'required|string|max:500',
            'notes'               => 'nullable|string|max:2000',
        ]);

        $transfer = $this->service->create($validated, $request->user());

        return $this->successResponse($transfer->load([
            'fromBudget:id,name',
            'fromBudgetLine:id,budget_id,total_amount,committed_amount,actual_amount',
            'toBudget:id,name',
            'toBudgetLine:id,budget_id,total_amount,committed_amount,actual_amount',
        ]), 'Budget transfer created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $transfer = BudgetTransfer::with([
            'fromBudget', 'fromBudgetLine', 'toBudget', 'toBudgetLine',
            'requester:id,name', 'approver:id,name',
        ])->findOrFail($id);

        return $this->successResponse($transfer);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $transfer = BudgetTransfer::findOrFail($id);
        $updated  = $this->service->submit($transfer);

        return $this->successResponse($updated, 'Budget transfer submitted for approval');
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:500',
        ]);

        $transfer = BudgetTransfer::findOrFail($id);

        if ($validated['action'] === 'approve') {
            $updated = $this->service->approve($transfer, $request->user());
            return $this->success($updated, 'Budget transfer approved and posted');
        }

        $updated = $this->service->reject($transfer, $request->user(), $validated['reason']);
        return $this->success($updated, 'Budget transfer rejected');
    }
}
