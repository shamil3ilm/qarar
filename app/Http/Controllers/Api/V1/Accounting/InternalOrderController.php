<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\InternalOrder;
use App\Services\Accounting\InternalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalOrderController extends Controller
{
    public function __construct(
        private readonly InternalOrderService $service
    ) {}

    /**
     * List internal orders with optional filters.
     *
     * GET /internal-orders
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'order_type', 'cost_center_id', 'search', 'per_page']);

        return $this->paginated($this->service->index($filters));
    }

    /**
     * Create a new internal order.
     *
     * POST /internal-orders
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'order_number'        => ['required', 'string', 'max:30'],
            'description'         => ['required', 'string', 'max:255'],
            'order_type'          => ['nullable', Rule::in([
                InternalOrder::TYPE_OVERHEAD,
                InternalOrder::TYPE_INVESTMENT,
                InternalOrder::TYPE_ACCRUAL,
                InternalOrder::TYPE_STATISTICAL,
            ])],
            'cost_center_id'      => ['nullable', 'integer', 'exists:cost_centers,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = $this->service->store(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($order->load(['costCenter:id,code,name', 'responsibleUser:id,name']));
    }

    /**
     * Show a single internal order with its settlements.
     *
     * GET /internal-orders/{internalOrder}
     */
    public function show(InternalOrder $internalOrder): JsonResponse
    {
        return $this->success(
            $internalOrder->load([
                'costCenter:id,code,name',
                'responsibleUser:id,name',
                'settlements',
            ])
        );
    }

    /**
     * Update an internal order (only when in 'created' status).
     *
     * PUT /internal-orders/{internalOrder}
     */
    public function update(Request $request, InternalOrder $internalOrder): JsonResponse
    {
        $validated = $request->validate([
            'description'         => ['sometimes', 'required', 'string', 'max:255'],
            'order_type'          => ['sometimes', 'required', Rule::in([
                InternalOrder::TYPE_OVERHEAD,
                InternalOrder::TYPE_INVESTMENT,
                InternalOrder::TYPE_ACCRUAL,
                InternalOrder::TYPE_STATISTICAL,
            ])],
            'cost_center_id'      => ['nullable', 'integer', 'exists:cost_centers,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $internalOrder->update($validated);

        return $this->success($internalOrder->fresh(['costCenter:id,code,name', 'responsibleUser:id,name']));
    }

    /**
     * Soft-delete an internal order (only when in 'created' status).
     *
     * DELETE /internal-orders/{internalOrder}
     */
    public function destroy(InternalOrder $internalOrder): JsonResponse
    {
        if (!$internalOrder->isCreated()) {
            return $this->error('Only orders in created status can be deleted.', 'INVALID_STATUS', 422);
        }

        $internalOrder->delete();

        return $this->success(['message' => 'Internal order deleted.']);
    }

    /**
     * Release an internal order (created -> released).
     *
     * POST /internal-orders/{internalOrder}/release
     */
    public function release(InternalOrder $internalOrder): JsonResponse
    {
        $order = $this->service->release($internalOrder);

        return $this->success($order);
    }

    /**
     * Run settlement for a released/technically-completed order.
     *
     * POST /internal-orders/{internalOrder}/settle
     */
    public function settle(InternalOrder $internalOrder): JsonResponse
    {
        $order = $this->service->settle($internalOrder);

        return $this->success($order);
    }

    /**
     * Mark an order as technically completed (released -> technically_completed).
     *
     * POST /internal-orders/{internalOrder}/technically-complete
     */
    public function technicallyComplete(InternalOrder $internalOrder): JsonResponse
    {
        $order = $this->service->technicallyComplete($internalOrder);

        return $this->success($order);
    }

    /**
     * Close an order (technically_completed -> closed).
     *
     * POST /internal-orders/{internalOrder}/close
     */
    public function close(InternalOrder $internalOrder): JsonResponse
    {
        $order = $this->service->close($internalOrder);

        return $this->success($order);
    }

    /**
     * Budget availability status for an internal order.
     *
     * GET /internal-orders/{internalOrder}/budget-status
     */
    public function budgetStatus(InternalOrder $internalOrder): JsonResponse
    {
        $status = $this->service->getBudgetStatus($internalOrder);

        return $this->success(array_merge(
            $internalOrder->only(['id', 'uuid', 'order_number', 'description', 'status']),
            ['budget_status' => $status]
        ));
    }

    /**
     * Plan vs actual variance report for an internal order.
     *
     * GET /internal-orders/{internalOrder}/variance?fiscal_year=2025
     */
    public function variance(Request $request, InternalOrder $internalOrder): JsonResponse
    {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $report = $this->service->getVarianceReport($internalOrder, (int) $request->fiscal_year);

        return $this->success($report);
    }
}
