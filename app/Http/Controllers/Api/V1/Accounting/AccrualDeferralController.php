<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccrualDeferral;
use App\Services\Accounting\AccrualDeferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccrualDeferralController extends Controller
{
    public function __construct(
        private AccrualDeferralService $service
    ) {}

    /**
     * List accrual/deferral entries.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = $this->service->index([
            ...$request->only(['status', 'type', 'start_date', 'end_date', 'per_page']),
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->paginated($entries);
    }

    /**
     * Create a new accrual or deferral entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference'         => ['required', 'string', 'max:50'],
            'type'              => ['required', 'in:accrual,deferral'],
            'debit_account_id'  => ['required', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'total_amount'      => ['required', 'numeric', 'min:0.0001'],
            'currency_code'     => ['nullable', 'string', 'size:3'],
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after:start_date'],
            'periods_total'     => ['required', 'integer', 'min:1'],
            'description'       => ['nullable', 'string'],
        ]);

        $entry = $this->service->store([
            ...$validated,
            'organization_id' => $this->organizationId($request),
            'created_by'      => auth()->id(),
        ]);

        return $this->created($entry, 'Accrual/deferral entry created successfully');
    }

    /**
     * Show a single accrual/deferral entry.
     */
    public function update(Request $request, AccrualDeferral $accrualDeferral): JsonResponse
    {
        if ($accrualDeferral->status !== AccrualDeferral::STATUS_ACTIVE) {
            return $this->error('Only active entries can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'description'   => ['nullable', 'string'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $accrualDeferral->update($validated);

        return $this->success($accrualDeferral->fresh(), 'Accrual/deferral entry updated successfully.');
    }

    public function show(AccrualDeferral $accrualDeferral): JsonResponse
    {
        $accrualDeferral->load(['debitAccount:id,code,name', 'creditAccount:id,code,name', 'createdBy:id,name']);

        return $this->success($accrualDeferral);
    }

    /**
     * Soft-delete an accrual/deferral entry (only active ones).
     */
    public function destroy(AccrualDeferral $accrualDeferral): JsonResponse
    {
        if ($accrualDeferral->status === AccrualDeferral::STATUS_COMPLETED) {
            return $this->error('Completed entries cannot be deleted.', 'INVALID_STATUS', 422);
        }

        $accrualDeferral->update(['status' => AccrualDeferral::STATUS_CANCELLED]);
        $accrualDeferral->delete();

        return $this->success(null, 'Accrual/deferral entry cancelled and deleted.');
    }

    /**
     * Post the next period for a given accrual/deferral entry.
     */
    public function postPeriod(Request $request, AccrualDeferral $accrualDeferral): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'integer', 'min:1'],
        ]);

        return $this->tryAction(
            fn() => $this->service->postPeriod($accrualDeferral, $validated['period']),
            'Period posted successfully.',
            'POST_PERIOD_FAILED'
        );
    }
}
