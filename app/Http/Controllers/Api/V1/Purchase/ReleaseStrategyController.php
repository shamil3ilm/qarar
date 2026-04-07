<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\ReleaseStrategy;
use App\Models\Purchase\ReleaseStrategyApproval;
use App\Models\Purchase\ReleaseStrategyLevel;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use App\Services\Purchase\ReleaseStrategyService;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseStrategyController extends Controller
{
    public function __construct(
        private readonly ReleaseStrategyService $releaseStrategyService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * List release strategies.
     */
    public function index(Request $request): JsonResponse
    {
        $strategies = $this->releaseStrategyService->list($request->only([
            'document_type',
            'is_active',
            'per_page',
        ]));

        return $this->paginated($strategies);
    }

    /**
     * Create a new release strategy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'document_type' => 'required|in:purchase_order,purchase_requisition',
            'is_active'     => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();

        $strategy = $this->releaseStrategyService->create($validated);

        return $this->created($strategy->load('levels'), 'Release strategy created successfully.');
    }

    /**
     * Show a release strategy.
     */
    public function show(ReleaseStrategy $releaseStrategy): JsonResponse
    {
        return $this->success($releaseStrategy->load('levels'));
    }

    /**
     * Update a release strategy.
     */
    public function update(Request $request, ReleaseStrategy $releaseStrategy): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'document_type' => 'sometimes|in:purchase_order,purchase_requisition',
            'is_active'     => 'boolean',
        ]);

        $strategy = $this->releaseStrategyService->update($releaseStrategy, $validated);

        return $this->success($strategy, 'Release strategy updated successfully.');
    }

    /**
     * Delete a release strategy.
     */
    public function destroy(ReleaseStrategy $releaseStrategy): JsonResponse
    {
        $this->releaseStrategyService->delete($releaseStrategy);

        return $this->success(null, 'Release strategy deleted successfully.');
    }

    /**
     * Add a level to a release strategy.
     */
    public function addLevel(Request $request, ReleaseStrategy $releaseStrategy): JsonResponse
    {
        $validated = $request->validate([
            'level'      => 'required|integer|min:1|max:255',
            'role'       => 'required|string|max:100',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'label'      => 'required|string|max:100',
        ]);

        try {
            $level = $this->releaseStrategyService->addLevel($releaseStrategy, $validated);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($level, 'Release strategy level added.');
    }

    /**
     * Remove a level from a release strategy.
     */
    public function removeLevel(ReleaseStrategy $releaseStrategy, ReleaseStrategyLevel $level): JsonResponse
    {
        if ($level->release_strategy_id !== $releaseStrategy->id) {
            return $this->error('Level does not belong to this strategy.', 'VALIDATION_ERROR', 422);
        }

        $this->releaseStrategyService->removeLevel($level);

        return $this->success(null, 'Release strategy level removed.');
    }

    /**
     * Get approval status for a document.
     */
    public function approvalStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|in:purchase_order,purchase_requisition',
            'document_id'   => 'required|integer|min:1',
        ]);

        $approvals = $this->releaseStrategyService->getApprovalStatus(
            $validated['document_type'],
            (int) $validated['document_id']
        );

        $fullyReleased = $this->releaseStrategyService->isFullyReleased(
            $validated['document_type'],
            (int) $validated['document_id']
        );

        return $this->success([
            'approvals'       => $approvals,
            'fully_released'  => $fullyReleased,
        ]);
    }

    /**
     * Approve a release approval record.
     * If all levels are approved, also marks the underlying PO/PR as approved.
     */
    public function approve(Request $request, ReleaseStrategyApproval $approval): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $approver = auth()->user();

        try {
            $fullyReleased = $this->releaseStrategyService->approve(
                $approval,
                $approver,
                $validated['comments'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $responseData = [
            'approval'       => $approval->fresh(['level', 'approver']),
            'fully_released' => $fullyReleased,
        ];

        if ($fullyReleased) {
            $responseData['message'] = 'Document fully released.';
            $this->markDocumentApproved($approval, $approver->id);
        }

        return $this->success($responseData, 'Approval recorded successfully.');
    }

    /**
     * Reject a release approval record.
     */
    public function reject(Request $request, ReleaseStrategyApproval $approval): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $approver = auth()->user();

        try {
            $this->releaseStrategyService->reject(
                $approval,
                $approver,
                $validated['comments'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(
            $approval->fresh(['level', 'approver']),
            'Approval rejected.'
        );
    }

    /**
     * After all levels are approved, mark the underlying document as approved/confirmed.
     */
    private function markDocumentApproved(ReleaseStrategyApproval $approval, int $userId): void
    {
        if ($approval->document_type === ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER) {
            $po = PurchaseOrder::find($approval->document_id);
            if ($po && $po->isPendingApproval()) {
                $this->purchaseOrderService->approvePO($po, $userId, 'Auto-approved via release strategy.');
            }
            return;
        }

        if ($approval->document_type === ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_REQUISITION) {
            $pr = PurchaseRequisition::find($approval->document_id);
            if ($pr && $pr->isPendingApproval()) {
                $pr->update([
                    'status'      => PurchaseRequisition::STATUS_APPROVED,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);
            }
        }
    }
}
