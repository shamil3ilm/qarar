<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockTransferResource;
use App\Models\Inventory\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function __construct(
        private StockTransferService $transferService
    ) {
    }

    /**
     * List stock transfers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'lines.product', 'lines.variant', 'creator'])
            ->latest()
            ->when($request->has('from_warehouse_id'), fn($q) => $q->fromWarehouse($request->integer('from_warehouse_id')))
            ->when($request->has('to_warehouse_id'), fn($q) => $q->toWarehouse($request->integer('to_warehouse_id')))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('from_date'), fn($q) => $q->where('transfer_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('transfer_date', '<=', $request->input('to_date')));

        $transfers = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($transfers, StockTransferResource::class);
    }

    /**
     * Create a new stock transfer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'transfer_date' => 'required|date',
            'expected_arrival_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.quantity_sent' => 'required_without:lines.*.quantity|numeric|gt:0',
            'lines.*.quantity' => 'required_without:lines.*.quantity_sent|numeric|gt:0',
            'lines.*.notes' => 'nullable|string|max:255',
        ]);

        // Map quantity to quantity_sent if needed
        $lines = array_map(function ($line) {
            if (!isset($line['quantity_sent']) && isset($line['quantity'])) {
                $line['quantity_sent'] = $line['quantity'];
            }
            unset($line['quantity']);
            return $line;
        }, $validated['lines']);

        try {
            $transfer = $this->transferService->create(
                collect($validated)->except('lines')->toArray(),
                $lines
            );

            return $this->created(new StockTransferResource($transfer), 'Stock transfer created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a stock transfer.
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load([
            'fromWarehouse', 'toWarehouse', 'lines.product', 'lines.variant',
            'creator', 'shipper', 'receiver',
        ]);

        return $this->success(new StockTransferResource($stockTransfer));
    }

    /**
     * Update a draft stock transfer.
     */
    public function update(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['sometimes', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'to_warehouse_id' => ['sometimes', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'transfer_date' => 'sometimes|date',
            'expected_arrival_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.quantity_sent' => 'required|numeric|gt:0',
            'lines.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $transfer = $this->transferService->update(
                $stockTransfer,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );

            return $this->success(new StockTransferResource($transfer), 'Stock transfer updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Ship a stock transfer.
     */
    public function ship(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->ship($stockTransfer, auth()->id());

            return $this->success(new StockTransferResource($transfer), 'Stock transfer shipped successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Receive a stock transfer.
     */
    public function receive(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'received_quantities' => 'nullable|array',
            'received_quantities.*' => 'numeric|min:0',
            'lines' => 'nullable|array',
            'lines.*.stock_transfer_line_id' => 'required_with:lines|integer',
            'lines.*.quantity_received' => 'required_with:lines|numeric|min:0',
        ]);

        // Support both formats: received_quantities (line_id => qty) and lines array
        $receivedQuantities = $validated['received_quantities'] ?? [];

        if (!empty($validated['lines'])) {
            foreach ($validated['lines'] as $line) {
                $receivedQuantities[$line['stock_transfer_line_id']] = $line['quantity_received'];
            }
        }

        try {
            $transfer = $this->transferService->receive(
                $stockTransfer,
                $receivedQuantities,
                auth()->id()
            );

            return $this->success(new StockTransferResource($transfer), 'Stock transfer received successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Cancel a stock transfer.
     */
    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->cancel($stockTransfer);

            return $this->success(new StockTransferResource($transfer), 'Stock transfer cancelled.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Get transfer summary.
     */
    public function summary(StockTransfer $stockTransfer): JsonResponse
    {
        $summary = $this->transferService->getSummary($stockTransfer);

        return $this->success($summary);
    }

    /**
     * Get pending transfers for a warehouse (or all pending transfers).
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
        ]);

        if ($request->has('warehouse_id')) {
            $pending = $this->transferService->getPendingForWarehouse(
                $request->integer('warehouse_id')
            );
            return $this->success($pending);
        }

        // Return all pending transfers (draft + in_transit)
        $pending = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'lines.product', 'creator'])
            ->whereIn('status', [StockTransfer::STATUS_DRAFT, StockTransfer::STATUS_IN_TRANSIT])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($pending, StockTransferResource::class);
    }

    /**
     * Get overdue transfers.
     */
    public function overdue(): JsonResponse
    {
        $overdue = $this->transferService->getOverdue();

        return $this->success($overdue);
    }
}
