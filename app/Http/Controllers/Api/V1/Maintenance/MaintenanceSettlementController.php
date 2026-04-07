<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceOrderCostLine;
use App\Services\Maintenance\MaintenanceOrderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceSettlementController extends Controller
{
    public function __construct(
        private MaintenanceOrderSettlementService $settlementService,
    ) {}

    public function costLines(Request $request, int $orderId): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = MaintenanceOrderCostLine::where('organization_id', $orgId)
            ->where('maintenance_order_id', $orderId)
            ->with(['costElement', 'vendor', 'employee'])
            ->orderBy('posting_date')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function addCostLine(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'cost_element_id' => 'nullable|integer|exists:cost_elements,id',
            'cost_type'       => 'required|in:labor,material,external,overhead',
            'quantity'        => 'nullable|numeric|min:0',
            'unit_cost'       => 'nullable|numeric|min:0',
            'total_cost'      => 'nullable|numeric|min:0',
            'currency_code'   => 'required|string|size:3',
            'posting_date'    => 'nullable|date',
            'vendor_id'       => 'nullable|integer|exists:contacts,id',
            'employee_id'     => 'nullable|integer|exists:employees,id',
        ]);

        // total_cost must be computable
        if (empty($validated['total_cost']) && (empty($validated['quantity']) || empty($validated['unit_cost']))) {
            return $this->error(
                'Either total_cost or both quantity and unit_cost are required.',
                'VALIDATION_ERROR',
                422,
            );
        }

        $line = $this->settlementService->recordCost([
            'maintenance_order_id' => $orderId,
            ...$validated,
        ]);

        return $this->created($line->load(['costElement', 'vendor', 'employee']));
    }

    public function totalCost(Request $request, int $orderId): JsonResponse
    {
        $costData = $this->settlementService->getTotalCost($orderId);

        return $this->success([
            'maintenance_order_id' => $orderId,
            ...$costData,
        ]);
    }

    public function settle(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'rules'                   => 'required|array|min:1',
            'rules.*.receiver_type'   => 'required|in:cost_center,asset,order,wbs',
            'rules.*.receiver_id'     => 'required|integer|min:1',
            'rules.*.percentage'      => 'required|numeric|min:0.01|max:100',
        ]);

        // Validate percentages sum to 100
        $total = array_sum(array_column($validated['rules'], 'percentage'));
        if (abs($total - 100) > 0.01) {
            return $this->error(
                'Settlement rule percentages must sum to 100. Got: ' . $total,
                'INVALID_PERCENTAGE',
                422,
            );
        }

        $settlements = $this->settlementService->settle($orderId, $validated['rules']);

        return $this->created($settlements);
    }

    public function settlementHistory(Request $request, int $orderId): JsonResponse
    {
        $history = $this->settlementService->getSettlementHistory($orderId);

        return $this->success($history);
    }

    public function unsettledOrders(Request $request): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $orders = $this->settlementService->getUnsettledOrders($orgId);

        return $this->success($orders);
    }
}
