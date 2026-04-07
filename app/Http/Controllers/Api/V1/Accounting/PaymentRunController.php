<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PaymentRun;
use App\Models\Accounting\PaymentRunItem;
use App\Services\Accounting\PaymentRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentRunController extends Controller
{
    public function __construct(
        private PaymentRunService $service
    ) {}

    /**
     * List payment runs.
     */
    public function index(Request $request): JsonResponse
    {
        $runs = $this->service->index([
            ...$request->only(['status', 'payment_direction', 'date_from', 'date_to', 'per_page']),
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->paginated($runs);
    }

    /**
     * Propose a new payment run.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_reference'     => ['required', 'string', 'max:50'],
            'payment_direction' => ['nullable', 'in:outgoing,incoming'],
            'payment_date'      => ['required', 'date'],
            'due_date_from'     => ['nullable', 'date'],
            'due_date_to'       => ['nullable', 'date', 'after_or_equal:due_date_from'],
            'vendor_filter'     => ['nullable', 'array'],
            'vendor_filter.*'   => ['integer'],
            'payment_methods'   => ['nullable', 'array'],
            'payment_methods.*' => ['string'],
            'minimum_payment'   => ['nullable', 'numeric', 'min:0'],
            'currency_code'     => ['nullable', 'string', 'size:3'],
            'bank_account_id'   => ['nullable', 'exists:bank_accounts,id'],
        ]);

        try {
            $run = $this->service->propose([
                ...$validated,
                'organization_id' => $this->organizationId($request),
                'created_by'      => auth()->id(),
            ]);

            return $this->created($run, 'Payment run proposed successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'PAYMENT_RUN_FAILED', 422);
        }
    }

    /**
     * Show a single payment run with its items.
     */
    public function update(Request $request, PaymentRun $paymentRun): JsonResponse
    {
        if ($paymentRun->status !== 'draft') {
            return $this->error('Only draft payment runs can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'payment_date'    => ['sometimes', 'date'],
            'due_date_from'   => ['nullable', 'date'],
            'due_date_to'     => ['nullable', 'date', 'after_or_equal:due_date_from'],
            'minimum_payment' => ['nullable', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        $paymentRun->update($validated);

        return $this->success($paymentRun->fresh(), 'Payment run updated successfully.');
    }

    public function show(PaymentRun $paymentRun): JsonResponse
    {
        $paymentRun->load(['items', 'createdBy:id,name', 'approvedBy:id,name', 'bankAccount:id,account_name']);

        return $this->success($paymentRun);
    }

    /**
     * Approve a proposed payment run.
     */
    public function approve(PaymentRun $paymentRun): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->approve($paymentRun),
            'Payment run approved.',
            'APPROVE_FAILED',
        );
    }

    /**
     * Post (execute) an approved payment run.
     */
    public function post(PaymentRun $paymentRun): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->post($paymentRun),
            'Payment run posted successfully.',
            'POST_FAILED',
        );
    }

    /**
     * Cancel a payment run.
     */
    public function cancel(PaymentRun $paymentRun): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->cancel($paymentRun),
            'Payment run cancelled.',
            'CANCEL_FAILED',
        );
    }

    /**
     * Exclude an item from a payment run.
     */
    public function excludeItem(Request $request, PaymentRun $paymentRun, PaymentRunItem $paymentRunItem): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($paymentRunItem->payment_run_id !== $paymentRun->id) {
            return $this->error('Item does not belong to this payment run.', 'ITEM_MISMATCH', 422);
        }

        return $this->tryAction(
            fn() => $this->service->excludeItem($paymentRunItem, $validated['reason']),
            'Item excluded from payment run.',
            'EXCLUDE_FAILED',
        );
    }

    /**
     * Soft-delete a draft payment run.
     */
    public function destroy(PaymentRun $paymentRun): JsonResponse
    {
        if (!in_array($paymentRun->status, [PaymentRun::STATUS_DRAFT, PaymentRun::STATUS_CANCELLED], true)) {
            return $this->error('Only draft or cancelled runs can be deleted.', 'INVALID_STATUS', 422);
        }

        $paymentRun->delete();

        return $this->success(null, 'Payment run deleted.');
    }
}
