<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Ecommerce\EcommerceSyncLog;
use Illuminate\Support\Facades\DB;

class EcommerceChannelService
{
    public function __construct() {}

    /**
     * Create a new e-commerce channel.
     */
    public function create(array $data): EcommerceChannel
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['status'] = $data['status'] ?? EcommerceChannel::STATUS_ACTIVE;

            return EcommerceChannel::create($data);
        });
    }

    /**
     * Update an existing channel.
     */
    public function update(EcommerceChannel $channel, array $data): EcommerceChannel
    {
        return DB::transaction(function () use ($channel, $data) {
            $channel->update($data);

            return $channel->fresh();
        });
    }

    /**
     * Sync channel data (products, orders, inventory).
     *
     * For order syncs, delegates to EcommerceOrderService which handles
     * fetching and importing via the platform adapter.  Other sync types
     * create a log entry and update the channel timestamp.
     */
    public function sync(EcommerceChannel $channel, string $syncType = 'orders'): EcommerceSyncLog
    {
        if (!$channel->isActive()) {
            throw new \InvalidArgumentException('Cannot sync inactive or disconnected channel.');
        }

        // Delegate order sync to the dedicated service
        if ($syncType === EcommerceSyncLog::TYPE_ORDERS) {
            $orderService = app(EcommerceOrderService::class);
            return $orderService->syncOrders($channel);
        }

        // For other sync types, create a log and mark completed.
        // Product/inventory/customer sync can be extended here.
        $syncLog = EcommerceSyncLog::create([
            'channel_id' => $channel->id,
            'sync_type' => $syncType,
            'direction' => EcommerceSyncLog::DIRECTION_PULL,
            'status' => EcommerceSyncLog::STATUS_STARTED,
            'started_at' => now(),
        ]);

        try {
            $channel->update(['last_sync_at' => now()]);

            $syncLog->update([
                'status' => EcommerceSyncLog::STATUS_COMPLETED,
                'total_records' => 0,
                'processed_records' => 0,
                'failed_records' => 0,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => EcommerceSyncLog::STATUS_FAILED,
                'errors' => ['message' => $e->getMessage()],
                'completed_at' => now(),
            ]);

            throw $e;
        }

        return $syncLog;
    }

    /**
     * Connect a channel (activate and validate credentials).
     *
     * Attempts a test connection to the external platform using the stored
     * credentials.  Throws if the platform is unreachable or returns an
     * auth error.
     */
    public function connect(EcommerceChannel $channel): EcommerceChannel
    {
        if ($channel->isActive()) {
            throw new \InvalidArgumentException('Channel is already connected.');
        }

        return DB::transaction(function () use ($channel) {
            $adapter = $this->resolveAdapter($channel);

            if (!$adapter->testConnection()) {
                throw new \InvalidArgumentException(
                    'Failed to connect to the e-commerce platform. Please verify your credentials and store URL.'
                );
            }

            $channel->update([
                'status' => EcommerceChannel::STATUS_ACTIVE,
            ]);

            return $channel->fresh();
        });
    }

    /**
     * Resolve the platform adapter for a channel.
     */
    protected function resolveAdapter(EcommerceChannel $channel): EcommerceChannelAdapter
    {
        $adapters = [
            EcommerceChannel::PLATFORM_SHOPIFY => ShopifyAdapter::class,
            EcommerceChannel::PLATFORM_WOOCOMMERCE => WooCommerceAdapter::class,
        ];

        $adapterClass = $adapters[$channel->platform] ?? NullChannelAdapter::class;

        return new $adapterClass($channel);
    }

    /**
     * Disconnect a channel.
     */
    public function disconnect(EcommerceChannel $channel): EcommerceChannel
    {
        if ($channel->status === EcommerceChannel::STATUS_DISCONNECTED) {
            throw new \InvalidArgumentException('Channel is already disconnected.');
        }

        return DB::transaction(function () use ($channel) {
            $channel->update([
                'status' => EcommerceChannel::STATUS_DISCONNECTED,
            ]);

            return $channel->fresh();
        });
    }

    /**
     * Get channel statistics.
     */
    public function getStats(EcommerceChannel $channel): array
    {
        $totalOrders = $channel->orders()->count();
        $pendingOrders = $channel->orders()->where('status', 'pending')->count();
        $processedOrders = $channel->orders()->where('is_processed', true)->count();
        $totalRevenue = $channel->orders()
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        $mappedProducts = $channel->productMappings()->count();
        $recentSyncs = $channel->syncLogs()
            ->latest()
            ->take(5)
            ->get();
        $failedSyncs = $channel->syncLogs()
            ->where('status', EcommerceSyncLog::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processed_orders' => $processedOrders,
            'total_revenue' => $totalRevenue,
            'mapped_products' => $mappedProducts,
            'last_sync_at' => $channel->last_sync_at?->toISOString(),
            'recent_syncs' => $recentSyncs,
            'failed_syncs_last_7_days' => $failedSyncs,
        ];
    }
}
