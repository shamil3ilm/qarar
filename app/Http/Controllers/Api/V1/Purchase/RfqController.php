<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\RfqHeader;
use App\Models\Purchase\RfqQuote;
use App\Models\Purchase\RfqVendor;
use App\Services\Purchase\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function __construct(
        private RfqService $rfqService
    ) {}

    /**
     * List RFQs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RfqHeader::with(['creator', 'vendors', 'items'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('rfq_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->start_date, fn($q, $date) => $q->where('submission_deadline', '>=', $date))
            ->when($request->end_date, fn($q, $date) => $q->where('submission_deadline', '<=', $date))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['rfq_number', 'title', 'status', 'submission_deadline', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)), \App\Http\Resources\Purchase\RfqResource::class);
    }

    /**
     * Create a new RFQ.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'rfq_number' => 'nullable|string|max:30',
            'submission_deadline' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'currency_code' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_id' => 'nullable|exists:units_of_measure,id',
            'items.*.notes' => 'nullable|string',
            'items.*.sort_order' => 'nullable|integer',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $rfq = $this->rfqService->createRfq($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new \App\Http\Resources\Purchase\RfqResource($rfq), 'RFQ created successfully.');
    }

    /**
     * Show an RFQ with full details.
     */
    public function show(RfqHeader $rfq): JsonResponse
    {
        return $this->success(
            new \App\Http\Resources\Purchase\RfqResource(
                $rfq->load(['items.product', 'vendors.contact', 'quotes.lines', 'creator'])
            )
        );
    }

    /**
     * Update a draft RFQ.
     */
    public function update(Request $request, RfqHeader $rfq): JsonResponse
    {
        if (!$rfq->isEditable()) {
            return $this->error('Only draft RFQs can be updated.', 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'submission_deadline' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'currency_code' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $rfq->update($validated);

        return $this->success(new \App\Http\Resources\Purchase\RfqResource($rfq->fresh(['items', 'vendors'])), 'RFQ updated successfully.');
    }

    /**
     * Send RFQ to vendors.
     */
    public function sendToVendors(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'vendor_ids' => 'required|array|min:1',
            'vendor_ids.*' => 'required|exists:contacts,id',
        ]);

        try {
            $rfq = $this->rfqService->sendToVendors($rfq, $validated['vendor_ids']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new \App\Http\Resources\Purchase\RfqResource($rfq), 'RFQ sent to vendors successfully.');
    }

    /**
     * Record a vendor quote against an RFQ.
     */
    public function recordQuote(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'rfq_vendor_id' => 'required|exists:rfq_vendors,id',
            'quote_number' => 'nullable|string|max:100',
            'quote_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'currency_code' => 'required|string|size:3',
            'total_amount' => 'nullable|numeric|min:0',
            'delivery_days' => 'nullable|integer|min:0',
            'payment_terms' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.rfq_item_id' => 'required|exists:rfq_items,id',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.delivery_days' => 'nullable|integer|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        $rfqVendor = RfqVendor::findOrFail($validated['rfq_vendor_id']);

        if ($rfqVendor->rfq_id !== $rfq->id) {
            return $this->error('Vendor invitation does not belong to this RFQ.', 'VALIDATION_ERROR', 422);
        }

        try {
            $quote = $this->rfqService->recordQuote($rfqVendor, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new \App\Http\Resources\Purchase\RfqQuoteResource($quote), 'Quote recorded successfully.');
    }

    /**
     * Get vendor quote comparison matrix.
     */
    public function compareQuotes(RfqHeader $rfq): JsonResponse
    {
        $matrix = $this->rfqService->compareQuotes($rfq);

        return $this->success($matrix);
    }

    /**
     * Award an RFQ to a vendor quote.
     */
    public function awardQuote(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'quote_id' => 'required|exists:rfq_quotes,id',
        ]);

        $quote = RfqQuote::findOrFail($validated['quote_id']);

        if ($quote->rfq_id !== $rfq->id) {
            return $this->error('Quote does not belong to this RFQ.', 'VALIDATION_ERROR', 422);
        }

        try {
            $quote = $this->rfqService->awardQuote($quote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new \App\Http\Resources\Purchase\RfqQuoteResource($quote), 'Quote awarded successfully.');
    }

    /**
     * Convert an awarded quote to a Purchase Order.
     */
    public function convertToPo(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'quote_id' => 'required|exists:rfq_quotes,id',
        ]);

        $quote = RfqQuote::findOrFail($validated['quote_id']);

        if ($quote->rfq_id !== $rfq->id) {
            return $this->error('Quote does not belong to this RFQ.', 'VALIDATION_ERROR', 422);
        }

        try {
            $purchaseOrder = $this->rfqService->convertToPurchaseOrder($quote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new \App\Http\Resources\Purchase\PurchaseOrderResource($purchaseOrder), 'Purchase order created from RFQ successfully.');
    }
}
