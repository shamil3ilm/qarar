<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CoReconciliationRun;
use App\Services\Accounting\CoReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CO Reconciliation Ledger Controller — SAP KALC.
 *
 * GET  /accounting/co-reconciliation                       index
 * GET  /accounting/co-reconciliation/{id}                  show
 * POST /accounting/co-reconciliation/reconcile-assessment  run reconciliation for assessment
 * POST /accounting/co-reconciliation/reconcile-distribution run reconciliation for distribution
 */
class CoReconciliationController extends Controller
{
    public function __construct(
        private readonly CoReconciliationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $runs = $this->service->getRuns(
            $request->user()->organization_id,
            $request->only(['fiscal_year', 'period', 'status']),
        );

        return $this->success($runs);
    }

    public function show(string $id): JsonResponse
    {
        $run = CoReconciliationRun::with(['entries.senderCostCenter', 'entries.receiverCostCenter', 'entries.costElement', 'postedBy:id,name'])->findOrFail($id);

        return $this->success($run);
    }

    public function reconcileAssessment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_cycle_id' => 'required|integer|exists:co_assessment_cycles,id',
            'fiscal_year'         => 'required|string|size:4',
            'period'              => 'required|string|size:2',
        ]);

        $run = $this->service->reconcileAssessment(
            $validated['assessment_cycle_id'],
            $validated['fiscal_year'],
            $validated['period'],
            $request->user(),
        );

        if ($run === null) {
            return $this->success(null, 'No cross-company postings found — reconciliation not required');
        }

        return $this->success($run->load('entries'), 'CO reconciliation entries posted', 201);
    }

    public function reconcileDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'distribution_cycle_id' => 'required|integer|exists:co_distribution_cycles,id',
            'fiscal_year'           => 'required|string|size:4',
            'period'                => 'required|string|size:2',
        ]);

        $run = $this->service->reconcileDistribution(
            $validated['distribution_cycle_id'],
            $validated['fiscal_year'],
            $validated['period'],
            $request->user(),
        );

        if ($run === null) {
            return $this->success(null, 'No cross-company postings found — reconciliation not required');
        }

        return $this->success($run->load('entries'), 'CO reconciliation entries posted', 201);
    }
}
