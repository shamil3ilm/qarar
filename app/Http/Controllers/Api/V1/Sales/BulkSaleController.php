<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\BulkSaleBatch;
use App\Services\Sales\BulkSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkSaleController extends Controller
{
    public function __construct(
        private BulkSaleService $bulkSaleService
    ) {}

    /**
     * List bulk sale batches.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BulkSaleBatch::with(['branch', 'creator'])
            ->latest('sale_date')
            ->when($request->has('status'), fn($q) => $q->byStatus($request->input('status')))
            ->when($request->has('branch_id'), fn($q) => $q->byBranch($request->integer('branch_id')))
            ->when($request->has('from_date'), fn($q) => $q->where('sale_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('sale_date', '<=', $request->input('to_date')));

        $batches = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($batches);
    }

    /**
     * Create a new bulk sale batch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'nullable|string|max:255',
            'sale_date' => 'required|date',
            'currency_code' => 'nullable|string|size:3',
            'auto_post' => 'boolean',
            'auto_send_email' => 'boolean',
            'generate_receipts' => 'boolean',
            'payment_method' => 'nullable|string|max:50',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.customer_id' => 'nullable|integer|exists:contacts,id',
            'items.*.customer_name' => 'nullable|string|max:255',
            'items.*.customer_email' => 'nullable|email|max:255',
            'items.*.customer_phone' => 'nullable|string|max:50',
            'items.*.customer_tax_number' => 'nullable|string|max:50',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.payment_status' => 'nullable|string|in:unpaid,paid,partial',
            'items.*.amount_paid' => 'nullable|numeric|min:0',
            'items.*.payment_reference' => 'nullable|string|max:255',
        ]);

        $items = $validated['items'] ?? [];
        $batchData = collect($validated)->except('items')->toArray();

        try {
            $batch = $this->bulkSaleService->createBatch($batchData, auth()->id());

            if (!empty($items)) {
                $batch = $this->bulkSaleService->addItems($batch, $items);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($batch->load(['items', 'branch', 'creator']), 'Bulk sale batch created successfully.');
    }

    /**
     * Show a batch.
     */
    public function show(BulkSaleBatch $bulkSaleBatch): JsonResponse
    {
        $bulkSaleBatch->load(['items.customer', 'items.product', 'items.invoice', 'branch', 'creator', 'bankAccount']);

        return $this->success($bulkSaleBatch);
    }

    /**
     * Update a draft batch.
     */
    public function update(Request $request, BulkSaleBatch $bulkSaleBatch): JsonResponse
    {
        if (!$bulkSaleBatch->isEditable()) {
            return $this->error('Only draft batches can be updated.', 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'sale_date' => 'sometimes|date',
            'currency_code' => 'nullable|string|size:3',
            'auto_post' => 'boolean',
            'auto_send_email' => 'boolean',
            'generate_receipts' => 'boolean',
            'payment_method' => 'nullable|string|max:50',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.customer_id' => 'nullable|integer|exists:contacts,id',
            'items.*.customer_name' => 'nullable|string|max:255',
            'items.*.customer_email' => 'nullable|email|max:255',
            'items.*.customer_phone' => 'nullable|string|max:50',
            'items.*.customer_tax_number' => 'nullable|string|max:50',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.payment_status' => 'nullable|string|in:unpaid,paid,partial',
            'items.*.amount_paid' => 'nullable|numeric|min:0',
            'items.*.payment_reference' => 'nullable|string|max:255',
        ]);

        $batchData = collect($validated)->except('items')->toArray();
        $bulkSaleBatch->update($batchData);

        if (isset($validated['items'])) {
            // Replace all items
            $bulkSaleBatch->items()->delete();
            $this->bulkSaleService->addItems($bulkSaleBatch, $validated['items']);
        }

        return $this->success(
            $bulkSaleBatch->fresh(['items', 'branch', 'creator']),
            'Bulk sale batch updated successfully.'
        );
    }

    /**
     * Delete a draft batch.
     */
    public function destroy(BulkSaleBatch $bulkSaleBatch): JsonResponse
    {
        if (!$bulkSaleBatch->isDraft()) {
            return $this->error('Only draft batches can be deleted.', 'VALIDATION_ERROR', 422);
        }

        $bulkSaleBatch->items()->delete();
        $bulkSaleBatch->delete();

        return $this->success(null, 'Bulk sale batch deleted successfully.');
    }

    /**
     * Process a batch.
     */
    public function process(BulkSaleBatch $bulkSaleBatch): JsonResponse
    {
        try {
            $batch = $this->bulkSaleService->processBatch($bulkSaleBatch);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        $message = match ($batch->status) {
            BulkSaleBatch::STATUS_COMPLETED => 'Batch processed successfully. All items completed.',
            BulkSaleBatch::STATUS_PARTIALLY_COMPLETED => "Batch partially completed. {$batch->success_count} succeeded, {$batch->failed_count} failed.",
            default => 'Batch processing failed.',
        };

        return $this->success($batch, $message);
    }

    /**
     * Cancel a batch.
     */
    public function cancel(Request $request, BulkSaleBatch $bulkSaleBatch): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->bulkSaleService->cancelBatch(
                $bulkSaleBatch,
                $validated['reason'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($batch, 'Batch cancelled successfully.');
    }

    /**
     * Get batch statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->bulkSaleService->getStats(
            $request->has('branch_id') ? $request->integer('branch_id') : null,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return $this->success($stats);
    }
}
