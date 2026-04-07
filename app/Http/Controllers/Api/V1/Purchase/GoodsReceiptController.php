<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\GoodsReceiptResource;
use App\Models\Purchase\Bill;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseOrder;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private GoodsReceiptService $goodsReceiptService
    ) {}

    /**
     * List goods receipts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with(['purchaseOrder', 'vendor', 'warehouse', 'creator'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->purchase_order_id, fn($q, $id) => $q->where('purchase_order_id', $id))
            ->when($request->warehouse_id, fn($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->start_date, fn($q, $date) => $q->where('gr_date', '>=', $date))
            ->when($request->end_date, fn($q, $date) => $q->where('gr_date', '<=', $date))
            ->when($request->search, function ($q, $search) {
                $q->where('gr_number', 'like', "%{$search}%");
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['gr_number', 'gr_date', 'status', 'created_at'], 'gr_date'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)), GoodsReceiptResource::class);
    }

    /**
     * Create a new Goods Receipt against a Purchase Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', Rule::exists('purchase_orders', 'id')->where('organization_id', auth()->user()->organization_id)],
            'gr_number' => 'nullable|string|max:30',
            'gr_date' => 'nullable|date',
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'lines' => 'required|array|min:1',
            'lines.*.po_line_id' => 'nullable|exists:purchase_order_lines,id',
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity_ordered' => 'nullable|numeric|min:0',
            'lines.*.quantity_received' => 'required|numeric|min:0.0001',
            'lines.*.quantity_rejected' => 'nullable|numeric|min:0',
            'lines.*.unit_id' => 'required|exists:units_of_measure,id',
            'lines.*.unit_cost' => 'required|numeric|min:0',
            'lines.*.location_id' => 'nullable|exists:warehouse_locations,id',
            'lines.*.batch_number' => 'nullable|string|max:100',
            'lines.*.expiry_date' => 'nullable|date',
        ]);

        $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        if ($purchaseOrder->organization_id !== auth()->user()->organization_id) {
            return $this->error('Purchase order not found.', 'NOT_FOUND', 404);
        }

        try {
            $gr = $this->goodsReceiptService->createGr($purchaseOrder, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new GoodsReceiptResource($gr), 'Goods receipt created successfully.');
    }

    /**
     * Show a goods receipt with all details.
     */
    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return $this->success(
            new GoodsReceiptResource(
                $goodsReceipt->load([
                    'purchaseOrder',
                    'vendor',
                    'warehouse',
                    'lines.product',
                    'lines.variant',
                    'lines.unit',
                    'lines.location',
                    'creator',
                    'journalEntry',
                ])
            )
        );
    }

    /**
     * Post a draft goods receipt: update stock and generate accounting entry.
     */
    public function post(GoodsReceipt $goodsReceipt): JsonResponse
    {
        try {
            $gr = $this->goodsReceiptService->postGr($goodsReceipt);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred while posting.', 'SERVER_ERROR', 500);
        }

        return $this->success(new GoodsReceiptResource($gr), 'Goods receipt posted successfully.');
    }

    /**
     * Reverse a posted goods receipt.
     */
    public function reverse(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $gr = $this->goodsReceiptService->reverseGr($goodsReceipt, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred while reversing.', 'SERVER_ERROR', 500);
        }

        return $this->success(new GoodsReceiptResource($gr), 'Goods receipt reversed successfully.');
    }

    /**
     * Run and return 3-way match results for a bill.
     */
    public function threeWayMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => 'required|exists:bills,id',
        ]);

        $bill = Bill::findOrFail($validated['bill_id']);

        if ($bill->organization_id !== auth()->user()->organization_id) {
            return $this->error('Bill not found.', 'NOT_FOUND', 404);
        }

        try {
            $result = $this->goodsReceiptService->runThreeWayMatch($bill);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred during matching.', 'SERVER_ERROR', 500);
        }

        return $this->success($result);
    }
}
