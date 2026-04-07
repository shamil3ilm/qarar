<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\LeaseContract;
use App\Services\Accounting\LeaseAccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseAccountingController extends Controller
{
    public function __construct(
        private readonly LeaseAccountingService $leaseService,
    ) {}

    /**
     * List lease contracts for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $leases = $this->leaseService->index($this->organizationId($request), $request->all());

        return $this->paginated($leases);
    }

    /**
     * Create a new lease contract (generates amortisation schedule).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'party_role'              => ['sometimes', 'in:lessee,lessor'],
            'asset_description'       => ['required', 'string', 'max:500'],
            'lessor_name'             => ['nullable', 'string', 'max:255'],
            'commencement_date'       => ['required', 'date'],
            'end_date'                => ['required', 'date', 'after:commencement_date'],
            'lease_term_months'       => ['required', 'integer', 'min:1', 'max:600'],
            'payment_amount'          => ['required', 'numeric', 'min:0.01'],
            'payment_frequency'       => ['sometimes', 'in:monthly,quarterly,semi_annual,annual'],
            'currency_code'           => ['sometimes', 'string', 'size:3'],
            'discount_rate'           => ['required', 'numeric', 'min:0', 'max:1'],
            'classification'          => ['sometimes', 'in:finance,operating,short_term,low_value'],
            'rou_asset_account_id'              => ['nullable', 'exists:chart_of_accounts,id'],
            'accum_depreciation_account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
            'lease_liability_account_id'        => ['nullable', 'exists:chart_of_accounts,id'],
            'interest_expense_account_id'       => ['nullable', 'exists:chart_of_accounts,id'],
            'depreciation_expense_account_id'   => ['nullable', 'exists:chart_of_accounts,id'],
            'notes'                   => ['nullable', 'string'],
        ]);

        try {
            $lease = $this->leaseService->create(
                $validated,
                $this->organizationId($request),
                $request->user()->id,
            );

            return $this->created($lease);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single lease contract with its schedule.
     */
    public function show(LeaseContract $leaseContract): JsonResponse
    {
        $leaseContract->load([
            'schedule',
            'rouAssetAccount:id,code,name',
            'leaseLiabilityAccount:id,code,name',
            'interestExpenseAccount:id,code,name',
            'depreciationExpenseAccount:id,code,name',
            'createdBy:id,name',
        ]);

        return $this->success($leaseContract);
    }

    /**
     * List the full amortisation schedule for a lease.
     */
    public function schedule(LeaseContract $leaseContract): JsonResponse
    {
        $schedule = $leaseContract->schedule()->with('journalEntry:id,entry_number')->get();

        return $this->success($schedule);
    }

    /**
     * Post journal entries for a specific period.
     */
    public function postPeriodEntry(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        $validated = $request->validate([
            'period_number' => ['required', 'integer', 'min:1'],
        ]);

        return $this->tryAction(
            fn() => $this->leaseService->postPeriodEntry(
                $leaseContract,
                $validated['period_number'],
                [
                    'organization_id' => $this->organizationId($request),
                    'branch_id'       => $request->header('X-Branch-Id'),
                    'entry_date'      => now()->toDateString(),
                    'source_type'     => LeaseContract::class,
                    'source_id'       => $leaseContract->id,
                ]
            ),
            'Period entry posted successfully.',
            'POST_FAILED'
        );
    }

    /**
     * Terminate a lease early.
     */
    public function terminate(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        $validated = $request->validate([
            'termination_date' => ['required', 'date'],
        ]);

        return $this->tryAction(
            fn() => $this->leaseService->terminate(
                $leaseContract,
                $validated['termination_date'],
                [
                    'organization_id' => $this->organizationId($request),
                    'branch_id'       => $request->header('X-Branch-Id'),
                    'entry_date'      => $validated['termination_date'],
                    'source_type'     => LeaseContract::class,
                    'source_id'       => $leaseContract->id,
                ]
            ),
            'Lease terminated successfully.',
            'TERMINATE_FAILED'
        );
    }

    /**
     * Modify / remeasure a lease (IFRS 16 para 45).
     */
    public function modify(Request $request, LeaseContract $leaseContract): JsonResponse
    {
        $validated = $request->validate([
            'payment_amount'    => ['sometimes', 'numeric', 'min:0.01'],
            'discount_rate'     => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'end_date'          => ['sometimes', 'date'],
            'lease_term_months' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->tryAction(
            fn() => $this->leaseService->modify($leaseContract, $validated),
            'Lease modified and schedule remeasured.',
            'MODIFY_FAILED'
        );
    }
}
