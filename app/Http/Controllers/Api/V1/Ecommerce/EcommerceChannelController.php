<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\EcommerceChannel;
use App\Services\Ecommerce\EcommerceChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceChannelController extends Controller
{
    public function __construct(
        private EcommerceChannelService $channelService
    ) {}

    /**
     * List e-commerce channels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EcommerceChannel::with(['defaultWarehouse', 'defaultCustomer'])
            ->latest()
            ->when($request->has('platform'), fn($q) => $q->byPlatform($request->input('platform')))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')));

        $channels = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($channels);
    }

    /**
     * Create a new e-commerce channel.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string|in:shopify,woocommerce,magento,custom,marketplace',
            'platform_name' => 'nullable|string|max:255',
            'store_url' => 'nullable|url|max:500',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'default_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'default_customer_id' => 'nullable|integer|exists:contacts,id',
            'sync_products' => 'boolean',
            'sync_orders' => 'boolean',
            'sync_inventory' => 'boolean',
            'auto_fulfill' => 'boolean',
        ]);

        $channel = $this->channelService->create($validated);

        return $this->created($channel, 'E-commerce channel created successfully.');
    }

    /**
     * Show a channel.
     */
    public function show(EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $ecommerceChannel->load(['defaultWarehouse', 'defaultCustomer']);

        return $this->success($ecommerceChannel);
    }

    /**
     * Update a channel.
     */
    public function update(Request $request, EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'platform_name' => 'nullable|string|max:255',
            'store_url' => 'nullable|url|max:500',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'default_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'default_customer_id' => 'nullable|integer|exists:contacts,id',
            'sync_products' => 'boolean',
            'sync_orders' => 'boolean',
            'sync_inventory' => 'boolean',
            'auto_fulfill' => 'boolean',
        ]);

        $channel = $this->channelService->update($ecommerceChannel, $validated);

        return $this->success($channel, 'E-commerce channel updated successfully.');
    }

    /**
     * Delete a channel.
     */
    public function destroy(EcommerceChannel $ecommerceChannel): JsonResponse
    {
        if ($ecommerceChannel->orders()->exists()) {
            return $this->error(
                'Cannot delete channel with existing orders.',
                'VALIDATION_ERROR',
                422
            );
        }

        $ecommerceChannel->delete();

        return $this->success(null, 'E-commerce channel deleted successfully.');
    }

    /**
     * Sync a channel.
     */
    public function sync(Request $request, EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $validated = $request->validate([
            'sync_type' => 'nullable|string|in:products,orders,inventory,customers',
        ]);

        $syncLog = $this->channelService->sync(
            $ecommerceChannel,
            $validated['sync_type'] ?? 'orders'
        );

        return $this->success($syncLog, 'Sync initiated successfully.');
    }

    /**
     * Connect a channel.
     */
    public function connect(EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $channel = $this->channelService->connect($ecommerceChannel);

        return $this->success($channel, 'Channel connected successfully.');
    }

    /**
     * Disconnect a channel.
     */
    public function disconnect(EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $channel = $this->channelService->disconnect($ecommerceChannel);

        return $this->success($channel, 'Channel disconnected successfully.');
    }

    /**
     * Get channel statistics.
     */
    public function stats(EcommerceChannel $ecommerceChannel): JsonResponse
    {
        $stats = $this->channelService->getStats($ecommerceChannel);

        return $this->success($stats);
    }
}
