<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Ecommerce\EcommerceOrder;
use App\Services\Ecommerce\EcommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceOrderController extends Controller
{
    public function __construct(
        private EcommerceOrderService $orderService
    ) {}

    /**
     * List e-commerce orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EcommerceOrder::with(['channel', 'customer'])
            ->latest('ordered_at')
            ->when($request->has('channel_id'), fn($q) => $q->byChannel($request->integer('channel_id')))
            ->when($request->has('status'), fn($q) => $q->byStatus($request->input('status')))
            ->when($request->has('from_date'), fn($q) => $q->where('ordered_at', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('ordered_at', '<=', $request->input('to_date')))
            ->when($request->boolean('unprocessed'), fn($q) => $q->unprocessed())
            ->when($request->has('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%");
                });
            });

        $orders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($orders);
    }

    /**
     * Show an order.
     */
    public function show(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $ecommerceOrder->load(['channel', 'customer', 'items.product', 'invoice', 'salesOrder']);

        return $this->success($ecommerceOrder);
    }

    /**
     * Import orders from a channel, or import a single order manually.
     *
     * When only channel_id is provided, triggers a sync/import from the channel.
     * When full order data is provided, imports a single order.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'channel_id' => 'required|integer|exists:ecommerce_channels,id',
        ]);

        $channel = EcommerceChannel::findOrFail($request->input('channel_id'));

        // If no individual order data is provided, trigger a channel sync
        if (!$request->has('external_order_id') && !$request->has('items')) {
            $syncLog = $this->orderService->syncOrders($channel);
            return $this->success($syncLog, 'Order import triggered successfully.');
        }

        // Single order import with full data
        $validated = $request->validate([
            'external_order_id' => 'required|string|max:255',
            'order_number' => 'required|string|max:255',
            'status' => 'nullable|string|in:pending,processing,shipped,delivered,cancelled,refunded',
            'financial_status' => 'nullable|string|in:pending,paid,partially_paid,refunded',
            'fulfillment_status' => 'nullable|string|in:unfulfilled,partial,fulfilled',
            'customer_email' => 'nullable|email|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_id' => 'nullable|integer|exists:contacts,id',
            'shipping_address' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'currency_code' => 'required|string|size:3',
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'shipping_method' => 'nullable|string|max:255',
            'ordered_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.external_product_id' => 'nullable|string|max:255',
            'items.*.external_variant_id' => 'nullable|string|max:255',
            'items.*.sku' => 'nullable|string|max:100',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'required|numeric|min:0',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
        ]);

        $items = $validated['items'];
        $orderData = collect($validated)->except('items')->toArray();

        $order = $this->orderService->importOrder($channel, $orderData, $items);

        return $this->created($order, 'Order imported successfully.');
    }

    /**
     * Process an e-commerce order.
     */
    public function process(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $order = $this->orderService->processOrder($ecommerceOrder);

        return $this->success($order, 'Order processed successfully.');
    }

    /**
     * Fulfill an e-commerce order.
     */
    public function fulfill(Request $request, EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'nullable|string|max:255',
            'tracking_url' => 'nullable|url|max:500',
        ]);

        $order = $this->orderService->fulfillOrder(
            $ecommerceOrder,
            $validated['tracking_number'] ?? null,
            $validated['tracking_url'] ?? null
        );

        return $this->success($order, 'Order fulfilled successfully.');
    }

    /**
     * Get order statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $channelId = $request->has('channel_id') ? $request->integer('channel_id') : null;
        $stats = $this->orderService->getOrderStats(auth()->user()->organization_id, $channelId);

        return $this->success($stats);
    }
}
