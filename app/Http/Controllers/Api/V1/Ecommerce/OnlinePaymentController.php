<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\OnlinePayment;
use App\Services\Ecommerce\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlinePaymentController extends Controller
{
    public function __construct(
        private OnlinePaymentService $paymentService
    ) {}

    /**
     * List online payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OnlinePayment::with(['gateway'])
            ->latest()
            ->when($request->has('gateway_id'), fn($q) => $q->byGateway($request->integer('gateway_id')))
            ->when($request->has('status'), fn($q) => $q->byStatus($request->input('status')))
            ->when($request->has('from_date'), fn($q) => $q->where('created_at', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('created_at', '<=', $request->input('to_date')));

        $payments = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($payments);
    }

    /**
     * Show a payment.
     */
    public function show(OnlinePayment $onlinePayment): JsonResponse
    {
        $onlinePayment->load(['gateway', 'payable']);

        return $this->success($onlinePayment);
    }

    /**
     * Process a payment callback.
     */
    public function callback(Request $request, OnlinePayment $onlinePayment): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'transaction_id' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:30',
            'card_brand' => 'nullable|string|max:50',
            'card_last4' => 'nullable|string|size:4',
            'fee_amount' => 'nullable|numeric|min:0',
            'failure_reason' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:500',
        ]);

        $payment = $this->paymentService->processCallback($onlinePayment, $validated);

        return $this->success($payment, 'Payment callback processed.');
    }

    /**
     * Refund a payment.
     */
    public function refund(Request $request, OnlinePayment $onlinePayment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        $payment = $this->paymentService->refund(
            $onlinePayment,
            $validated['amount'] ?? null,
            $validated['reason'] ?? null
        );

        return $this->success($payment, 'Payment refunded successfully.');
    }

    /**
     * Get payment status.
     */
    public function status(OnlinePayment $onlinePayment): JsonResponse
    {
        $status = $this->paymentService->getStatus($onlinePayment);

        return $this->success($status);
    }
}
