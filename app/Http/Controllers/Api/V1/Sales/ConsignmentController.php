<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Sales\ConsignmentOrder;
use App\Models\Sales\ConsignmentStock;
use App\Models\Sales\Contact;
use App\Services\Sales\ConsignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsignmentController extends Controller
{
    public function __construct(
        private ConsignmentService $consignmentService
    ) {}

    /**
     * List consignment orders with optional type / status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConsignmentOrder::with(['contact', 'creator'])
            ->latest('order_date')
            ->when($request->has('order_type'), fn($q) => $q->byType($request->input('order_type')))
            ->when($request->has('status'), fn($q) => $q->byStatus($request->input('status')))
            ->when($request->has('contact_id'), fn($q) => $q->where('contact_id', $request->integer('contact_id')))
            ->when($request->has('from_date'), fn($q) => $q->where('order_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('order_date', '<=', $request->input('to_date')));

        $orders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($orders);
    }

    /**
     * Create a new consignment order.
     * The `order_type` field determines which workflow is triggered:
     * fillup | issue | pickup | return
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_type'    => 'required|in:fillup,issue,pickup,return',
            'contact_id'    => 'required|integer|exists:contacts,id',
            'order_date'    => 'required|date',
            'branch_id'     => 'nullable|integer|exists:branches,id',
            'notes'         => 'nullable|string|max:5000',
            'lines'         => 'required|array|min:1',
            'lines.*.product_id'  => 'required|integer|exists:products,id',
            'lines.*.variant_id'  => 'nullable|integer|exists:product_variants,id',
            'lines.*.quantity'    => 'required|numeric|min:0.0001',
            'lines.*.unit_id'     => 'required|integer|exists:units_of_measure,id',
            'lines.*.unit_price'  => 'nullable|numeric|min:0',
            'lines.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.notes'       => 'nullable|string|max:200',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by']      = auth()->id();

        $order = match ($validated['order_type']) {
            ConsignmentOrder::TYPE_FILLUP  => $this->consignmentService->createFillup($validated),
            ConsignmentOrder::TYPE_ISSUE   => $this->consignmentService->createIssue($validated),
            ConsignmentOrder::TYPE_PICKUP  => $this->consignmentService->createPickup($validated),
            ConsignmentOrder::TYPE_RETURN  => $this->consignmentService->createReturn($validated),
        };

        return $this->success($order, 'Consignment order created.', 201);
    }

    /**
     * Show a consignment order with lines.
     */
    public function show(ConsignmentOrder $consignment): JsonResponse
    {
        $consignment->load(['lines.product', 'lines.variant', 'lines.unit', 'contact', 'creator', 'branch', 'invoice']);

        return $this->success($consignment);
    }

    /**
     * Confirm a draft consignment order.
     */
    public function confirm(ConsignmentOrder $consignment): JsonResponse
    {
        $order = $this->consignmentService->confirm($consignment);

        return $this->success($order, 'Consignment order confirmed.');
    }

    /**
     * Mark a confirmed consignment order as shipped.
     */
    public function ship(ConsignmentOrder $consignment): JsonResponse
    {
        $order = $this->consignmentService->ship($consignment);

        return $this->success($order, 'Consignment order shipped.');
    }

    /**
     * Complete a consignment order, posting stock movements.
     * For Issue orders, an invoice is also generated automatically.
     */
    public function complete(ConsignmentOrder $consignment): JsonResponse
    {
        $order = $this->consignmentService->complete($consignment);

        return $this->success($order, 'Consignment order completed.');
    }

    /**
     * Cancel a draft or confirmed consignment order.
     */
    public function cancel(ConsignmentOrder $consignment): JsonResponse
    {
        $order = $this->consignmentService->cancel($consignment);

        return $this->success($order, 'Consignment order cancelled.');
    }

    /**
     * Get current consignment stock levels.
     * Query parameters: contact_id (required), product_id (optional)
     */
    public function stockLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        $organizationId = $this->organizationId($request);

        $query = ConsignmentStock::with(['product', 'variant', 'warehouse'])
            ->where('organization_id', $organizationId)
            ->where('contact_id', $validated['contact_id']);

        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        $stocks = $query->get();

        return $this->success($stocks);
    }

    /**
     * Get a consignment statement for a contact: stock summary + recent movements.
     */
    public function statement(Request $request, int $contactId): JsonResponse
    {
        $contact = Contact::findOrFail($contactId);

        $statement = $this->consignmentService->getConsignmentStatement($contact);

        return $this->success($statement);
    }
}
