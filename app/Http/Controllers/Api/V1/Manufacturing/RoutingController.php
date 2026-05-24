<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\RoutingHeader;
use App\Services\Manufacturing\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutingController extends Controller
{
    public function __construct(
        private RoutingService $routingService,
    ) {}

    /**
     * List routing headers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $routings = $this->routingService->index($request->only([
            'product_id', 'is_default', 'valid', 'per_page',
        ]));

        return $this->paginated($routings);
    }

    /**
     * Create a new routing header with operations.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'              => ['required', 'integer', 'exists:products,id'],
            'routing_number'          => ['nullable', 'string', 'max:30'],
            'alternative'             => ['nullable', 'string', 'max:5'],
            'is_default'              => ['nullable', 'boolean'],
            'valid_from'              => ['nullable', 'date'],
            'valid_to'                => ['nullable', 'date', 'after_or_equal:valid_from'],
            'operations'              => ['nullable', 'array'],
            'operations.*.operation_code'  => ['required_with:operations', 'string', 'max:20'],
            'operations.*.description'     => ['required_with:operations', 'string', 'max:255'],
            'operations.*.work_center_id'  => ['required_with:operations', 'integer', 'exists:work_centers,id'],
            'operations.*.sequence_number' => ['nullable', 'integer', 'min:1'],
            'operations.*.setup_time'      => ['nullable', 'numeric', 'min:0'],
            'operations.*.machine_time'    => ['nullable', 'numeric', 'min:0'],
            'operations.*.labor_time'      => ['nullable', 'numeric', 'min:0'],
            'operations.*.control_key'     => ['nullable', 'string', 'max:10'],
        ]);

        $routing = $this->routingService->store($validated);

        return $this->created($routing);
    }

    /**
     * Show a single routing header with its operations.
     */
    public function show(RoutingHeader $routing): JsonResponse
    {
        $routing->load(['product', 'operations.workCenter']);

        return $this->success($routing);
    }

    /**
     * Update a routing header's metadata (not its operations).
     */
    public function update(Request $request, RoutingHeader $routing): JsonResponse
    {
        $validated = $request->validate([
            'routing_number' => ['nullable', 'string', 'max:30'],
            'alternative'    => ['nullable', 'string', 'max:5'],
            'is_default'     => ['nullable', 'boolean'],
            'valid_from'     => ['nullable', 'date'],
            'valid_to'       => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $routing->update($validated);

        return $this->success($routing->fresh(['product', 'operations.workCenter']));
    }

    /**
     * Soft-delete a routing header.
     */
    public function destroy(RoutingHeader $routing): JsonResponse
    {
        $this->routingService->destroy($routing);

        return $this->success(null, 'Routing deleted successfully.');
    }

    /**
     * Add an operation to a routing header.
     */
    public function addOperation(Request $request, RoutingHeader $routingHeader): JsonResponse
    {
        $validated = $request->validate([
            'operation_code'  => ['required', 'string', 'max:20'],
            'description'     => ['required', 'string', 'max:255'],
            'work_center_id'  => ['required', 'integer', 'exists:work_centers,id'],
            'sequence_number' => ['nullable', 'integer', 'min:1'],
            'setup_time'      => ['nullable', 'numeric', 'min:0'],
            'machine_time'    => ['nullable', 'numeric', 'min:0'],
            'labor_time'      => ['nullable', 'numeric', 'min:0'],
            'control_key'     => ['nullable', 'string', 'max:10'],
        ]);

        $operation = $this->routingService->addOperation($routingHeader, $validated);

        return $this->created($operation->load('workCenter'));
    }

    /**
     * Calculate production lead time for a product at a given quantity.
     */
    public function calculateLeadTime(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $hours = $this->routingService->calculateLeadTime($productId, (float) $validated['quantity']);

        return $this->success([
            'product_id'  => $productId,
            'quantity'    => $validated['quantity'],
            'lead_time_hours' => $hours,
            'lead_time_days'  => round($hours / 8, 2),
        ]);
    }
}
