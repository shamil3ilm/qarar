<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\PaymentMode;
use App\Services\Sales\PaymentDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentModeController extends Controller
{
    public function __construct(private PaymentDeliveryService $service) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->getPaymentModes(auth()->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30',
            'type' => 'required|string|in:cash,bank_transfer,card,cheque,upi,mobile_wallet,online,crypto,credit_term,cod,wallet',
            'description' => 'nullable|string',
            'is_online' => 'nullable|boolean',
            'requires_reference' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'surcharge_percent' => 'nullable|numeric|min:0',
            'surcharge_flat' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'gateway_provider' => 'nullable|string',
            'gateway_config' => 'nullable|array',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $mode = $this->service->createPaymentMode($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($mode);
    }

    public function show(PaymentMode $mode): JsonResponse
    {
        return $this->success($mode);
    }

    public function update(Request $request, PaymentMode $mode): JsonResponse
    {
        $mode->update($request->all());
        return $this->success($mode->fresh());
    }

    public function destroy(PaymentMode $mode): JsonResponse
    {
        $mode->delete();
        return $this->success(['message' => 'Payment mode deleted']);
    }
}
