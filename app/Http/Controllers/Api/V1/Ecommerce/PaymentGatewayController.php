<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    /**
     * List payment gateways.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentGateway::query()->latest()
            ->when($request->has('provider'), fn($q) => $q->byProvider($request->input('provider')))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('mode'), fn($q) => $q->where('mode', $request->input('mode')));

        $gateways = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($gateways);
    }

    /**
     * Create a new payment gateway.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|in:stripe,paypal,tap,moyasar,hyperpay,mada',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'mode' => 'required|string|in:test,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'supported_methods' => 'nullable|array',
            'supported_methods.*' => 'string|max:30',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $gateway = PaymentGateway::create($validated);

        if ($validated['is_default'] ?? false) {
            $gateway->setAsDefault();
        }

        return $this->created($gateway, 'Payment gateway created successfully.');
    }

    /**
     * Show a payment gateway.
     */
    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        return $this->success($paymentGateway);
    }

    /**
     * Update a payment gateway.
     */
    public function update(Request $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'mode' => 'sometimes|string|in:test,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'supported_methods' => 'nullable|array',
            'supported_methods.*' => 'string|max:30',
        ]);

        $paymentGateway->update($validated);

        if ($validated['is_default'] ?? false) {
            $paymentGateway->setAsDefault();
        }

        return $this->success($paymentGateway->fresh(), 'Payment gateway updated successfully.');
    }

    /**
     * Delete a payment gateway.
     */
    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        if ($paymentGateway->payments()->exists()) {
            return $this->error(
                'Cannot delete gateway with existing payments.',
                'VALIDATION_ERROR',
                422
            );
        }

        $paymentGateway->delete();

        return $this->success(null, 'Payment gateway deleted successfully.');
    }
}
