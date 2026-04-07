<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\SupplierDeliveryRecord;
use App\Models\Purchase\SupplierEvaluationCriteria;
use App\Models\Purchase\SupplierIncident;
use App\Models\Purchase\SupplierScorecard;
use App\Services\Purchase\SupplierPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPerformanceController extends Controller
{
    public function __construct(
        private readonly SupplierPerformanceService $performanceService
    ) {}

    // -------------------------------------------------------------------------
    // Evaluation Criteria
    // -------------------------------------------------------------------------

    public function indexCriteria(Request $request): JsonResponse
    {
        $query = SupplierEvaluationCriteria::query()
            ->when($request->category, fn($q, $c) => $q->forCategory($c))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('category')
            ->orderBy('name');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 50)),
            null
        );
    }

    public function storeCriteria(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'category'       => 'required|in:quality,delivery,price,service,compliance',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'is_active'      => 'nullable|boolean',
        ]);

        $criteria = $this->performanceService->createCriteria(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created($criteria, 'Evaluation criteria created.');
    }

    public function updateCriteria(Request $request, int $id): JsonResponse
    {
        $criteria = SupplierEvaluationCriteria::find($id);

        if ($criteria === null) {
            return $this->notFound('Criteria not found.');
        }

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'category'       => 'sometimes|in:quality,delivery,price,service,compliance',
            'weight_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active'      => 'sometimes|boolean',
        ]);

        $updated = $this->performanceService->updateCriteria($criteria, $validated, (int) auth()->id());

        return $this->success($updated, 'Evaluation criteria updated.');
    }

    public function destroyCriteria(int $id): JsonResponse
    {
        $criteria = SupplierEvaluationCriteria::find($id);

        if ($criteria === null) {
            return $this->notFound('Criteria not found.');
        }

        $criteria->delete();

        return $this->success(null, 'Evaluation criteria deleted.');
    }

    // -------------------------------------------------------------------------
    // Scorecards
    // -------------------------------------------------------------------------

    public function indexScorecards(Request $request): JsonResponse
    {
        $query = SupplierScorecard::with(['supplier', 'evaluator'])
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->from, fn($q, $d) => $q->where('evaluation_period_start', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->where('evaluation_period_end', '<=', $d))
            ->orderBy('evaluation_period_start', 'desc');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }

    public function storeScorecard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'             => 'required|integer|exists:sales_contacts,id',
            'evaluation_period_start' => 'required|date',
            'evaluation_period_end'   => 'required|date|after_or_equal:evaluation_period_start',
            'notes'                   => 'nullable|string',
            'ratings'                 => 'required|array|min:1',
            'ratings.*.criterion_id'  => 'required|integer|exists:supplier_evaluation_criteria,id',
            'ratings.*.score'         => 'required|numeric|min:0|max:100',
            'ratings.*.comments'      => 'nullable|string',
        ]);

        $scorecard = $this->performanceService->createScorecard(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created($scorecard, 'Scorecard created.');
    }

    public function showScorecard(int $id): JsonResponse
    {
        $scorecard = SupplierScorecard::with(['supplier', 'evaluator', 'ratings.criterion'])->find($id);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        return $this->success($scorecard);
    }

    public function updateScorecard(Request $request, int $id): JsonResponse
    {
        $scorecard = SupplierScorecard::find($id);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        if (!$scorecard->isDraft()) {
            return $this->error('Only draft scorecards can be updated.', 'SCORECARD_NOT_EDITABLE', 422);
        }

        $validated = $request->validate([
            'supplier_id'             => 'sometimes|integer|exists:sales_contacts,id',
            'evaluation_period_start' => 'sometimes|date',
            'evaluation_period_end'   => 'sometimes|date|after_or_equal:evaluation_period_start',
            'notes'                   => 'nullable|string',
            'ratings'                 => 'sometimes|array|min:1',
            'ratings.*.criterion_id'  => 'required_with:ratings|integer|exists:supplier_evaluation_criteria,id',
            'ratings.*.score'         => 'required_with:ratings|numeric|min:0|max:100',
            'ratings.*.comments'      => 'nullable|string',
        ]);

        $updated = $this->performanceService->updateScorecard($scorecard, $validated, (int) auth()->id());

        return $this->success($updated, 'Scorecard updated.');
    }

    public function finalizeScorecard(int $id): JsonResponse
    {
        $scorecard = SupplierScorecard::find($id);

        if ($scorecard === null) {
            return $this->notFound('Scorecard not found.');
        }

        if ($scorecard->isFinalized()) {
            return $this->error('Scorecard is already finalized.', 'SCORECARD_ALREADY_FINALIZED', 422);
        }

        $finalized = $this->performanceService->finalizeScorecard($scorecard, (int) auth()->id());

        return $this->success($finalized, 'Scorecard finalized.');
    }

    // -------------------------------------------------------------------------
    // Delivery Records
    // -------------------------------------------------------------------------

    public function indexDeliveryRecords(Request $request): JsonResponse
    {
        $query = SupplierDeliveryRecord::with(['supplier', 'purchaseOrder'])
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->from, fn($q, $d) => $q->where('promised_date', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->where('promised_date', '<=', $d))
            ->when(
                $request->has('is_on_time'),
                fn($q) => $q->where('is_on_time', filter_var($request->is_on_time, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('promised_date', 'desc');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }

    public function storeDeliveryRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'supplier_id'       => 'required|integer|exists:sales_contacts,id',
            'promised_date'     => 'required|date',
            'actual_date'       => 'nullable|date',
            'quantity_ordered'  => 'required|numeric|min:0',
            'quantity_received' => 'nullable|numeric|min:0',
            'quality_accepted'  => 'nullable|boolean',
            'defect_quantity'   => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string|max:500',
        ]);

        $record = $this->performanceService->recordDelivery(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created($record, 'Delivery record created.');
    }

    // -------------------------------------------------------------------------
    // Incidents
    // -------------------------------------------------------------------------

    public function indexIncidents(Request $request): JsonResponse
    {
        $query = SupplierIncident::with(['supplier', 'createdBy'])
            ->when($request->supplier_id, fn($q, $id) => $q->forSupplier($id))
            ->when($request->severity, fn($q, $s) => $q->ofSeverity($s))
            ->when($request->incident_type, fn($q, $t) => $q->where('incident_type', $t))
            ->when($request->boolean('open_only'), fn($q) => $q->open())
            ->when($request->from, fn($q, $d) => $q->where('occurred_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->where('occurred_at', '<=', $d))
            ->orderBy('occurred_at', 'desc');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'   => 'required|integer|exists:sales_contacts,id',
            'incident_type' => 'required|in:late_delivery,quality_issue,pricing_dispute,compliance_breach,communication',
            'severity'      => 'required|in:low,medium,high,critical',
            'description'   => 'required|string',
            'occurred_at'   => 'required|date',
        ]);

        $incident = $this->performanceService->createIncident(
            (int) auth()->user()->organization_id,
            $validated,
            (int) auth()->id()
        );

        return $this->created($incident, 'Incident recorded.');
    }

    public function resolveIncident(Request $request, int $id): JsonResponse
    {
        $incident = SupplierIncident::find($id);

        if ($incident === null) {
            return $this->notFound('Incident not found.');
        }

        if ($incident->isResolved()) {
            return $this->error('Incident is already resolved.', 'INCIDENT_ALREADY_RESOLVED', 422);
        }

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $resolved = $this->performanceService->resolveIncident(
            $incident,
            $validated['resolution_notes'],
            (int) auth()->id()
        );

        return $this->success($resolved, 'Incident resolved.');
    }

    // -------------------------------------------------------------------------
    // Analytics
    // -------------------------------------------------------------------------

    public function supplierStats(Request $request, int $supplierId): JsonResponse
    {
        $stats = $this->performanceService->getSupplierStats(
            (int) auth()->user()->organization_id,
            $supplierId
        );

        return $this->success($stats);
    }

    public function supplierRanking(Request $request): JsonResponse
    {
        $ranking = $this->performanceService->getSupplierRanking(
            (int) auth()->user()->organization_id
        );

        return $this->success($ranking);
    }
}
