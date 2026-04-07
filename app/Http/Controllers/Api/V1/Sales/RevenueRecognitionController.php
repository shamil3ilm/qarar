<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\PerformanceObligation;
use App\Models\Sales\RevenueContract;
use App\Services\Sales\RevenueRecognitionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RevenueRecognitionController extends Controller
{
    public function __construct(
        private RevenueRecognitionService $revenueRecognitionService
    ) {}

    /**
     * List revenue contracts.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $query = RevenueContract::with(['customer', 'performanceObligations'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->contact_id, fn($q, $v) => $q->where('contact_id', $v))
            ->when($request->search, fn($q, $s) => $q->where('contract_number', 'like', "%{$s}%"))
            ->orderBy('contract_date', 'desc');

        $contracts = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($contracts);
    }

    /**
     * Create a new revenue contract with obligations.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'contract_number'         => ['required', 'string', 'max:100', Rule::unique('revenue_contracts')->where('organization_id', $orgId)],
            'contact_id'              => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'contract_date'           => 'required|date',
            'total_transaction_price' => 'required|numeric|min:0',
            'recognition_method'      => ['required', Rule::in([RevenueContract::METHOD_POINT_IN_TIME, RevenueContract::METHOD_OVER_TIME])],
            'start_date'              => 'nullable|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'status'                  => ['nullable', Rule::in([RevenueContract::STATUS_DRAFT, RevenueContract::STATUS_ACTIVE])],

            'obligations'                                      => 'required|array|min:1',
            'obligations.*.description'                        => 'required|string|max:500',
            'obligations.*.standalone_selling_price'           => 'required|numeric|min:0',
            'obligations.*.recognition_method'                 => ['required', Rule::in([
                PerformanceObligation::METHOD_POINT_IN_TIME,
                PerformanceObligation::METHOD_OVER_TIME,
                PerformanceObligation::METHOD_MILESTONE,
            ])],
            'obligations.*.revenue_account_id'                 => 'nullable|exists:chart_of_accounts,id',
            'obligations.*.deferred_account_id'                => 'nullable|exists:chart_of_accounts,id',
        ]);

        $contractData = array_merge(
            array_except($validated, ['obligations']),
            ['organization_id' => $orgId, 'status' => $validated['status'] ?? RevenueContract::STATUS_DRAFT]
        );

        $contract = $this->revenueRecognitionService->createContract(
            $contractData,
            $validated['obligations'],
            auth()->id()
        );

        return $this->success($contract->load('performanceObligations'), 'Revenue contract created.', 201);
    }

    /**
     * Show a single revenue contract.
     */
    public function show(RevenueContract $revenueContract): JsonResponse
    {
        $revenueContract->load(['customer', 'performanceObligations.recognitionEvents.journalEntry']);

        return $this->success($revenueContract);
    }

    /**
     * Update a draft revenue contract.
     */
    public function update(Request $request, RevenueContract $revenueContract): JsonResponse
    {
        if (!$revenueContract->isDraft()) {
            return $this->error('Only draft contracts can be updated.', 422);
        }

        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'contract_date'           => 'sometimes|date',
            'total_transaction_price' => 'sometimes|numeric|min:0',
            'recognition_method'      => ['sometimes', Rule::in([RevenueContract::METHOD_POINT_IN_TIME, RevenueContract::METHOD_OVER_TIME])],
            'start_date'              => 'nullable|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'status'                  => ['sometimes', Rule::in([
                RevenueContract::STATUS_DRAFT,
                RevenueContract::STATUS_ACTIVE,
                RevenueContract::STATUS_CANCELLED,
            ])],
        ]);

        $revenueContract->update($validated);

        return $this->success($revenueContract->fresh('performanceObligations'), 'Contract updated.');
    }

    /**
     * Allocate transaction price across obligations.
     */
    public function allocate(RevenueContract $revenueContract): JsonResponse
    {
        $this->revenueRecognitionService->allocateTransactionPrice($revenueContract);

        return $this->success(
            $revenueContract->fresh('performanceObligations'),
            'Transaction price allocated.'
        );
    }

    /**
     * Recognize revenue for a specific performance obligation.
     */
    public function recognize(Request $request, PerformanceObligation $performanceObligation): JsonResponse
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'event_date'     => 'nullable|date',
            'method'         => ['nullable', Rule::in(['point_in_time', 'progress'])],
            'completion_pct' => 'required_if:method,progress|nullable|numeric|min:0|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        $method = $validated['method'] ?? 'amount';
        $userId = auth()->id();

        $event = match ($method) {
            'point_in_time' => $this->revenueRecognitionService->recognizeAtPointInTime(
                $performanceObligation,
                $userId
            ),
            'progress' => $this->revenueRecognitionService->recognizeProgressBased(
                $performanceObligation,
                (float) $validated['completion_pct'],
                $userId
            ),
            default => $this->revenueRecognitionService->recognizeRevenue(
                $performanceObligation,
                (float) $validated['amount'],
                isset($validated['event_date']) ? Carbon::parse($validated['event_date']) : Carbon::today(),
                $userId
            ),
        };

        return $this->success($event->load('journalEntry'), 'Revenue recognized.', 201);
    }

    /**
     * Get deferred revenue balance for the authenticated organization.
     */
    public function deferredBalance(): JsonResponse
    {
        $orgId = auth()->user()->organization_id;
        $balances = $this->revenueRecognitionService->getDeferredRevenueBalance($orgId);

        return $this->success([
            'balances' => $balances,
            'total_deferred' => array_sum(array_column($balances, 'deferred_amount')),
        ]);
    }
}
