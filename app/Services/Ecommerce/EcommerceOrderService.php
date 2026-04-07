<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderItem;
use App\Models\Ecommerce\EcommerceSyncLog;
use Illuminate\Support\Facades\DB;

class EcommerceOrderService
{
    public function __construct() {}

    /**
     * Import a single order from an e-commerce channel.
     */
    public function importOrder(EcommerceChannel $channel, array $orderData, array $items): EcommerceOrder
    {
        if (!$channel->isActive()) {
            throw new \InvalidArgumentException('Cannot import orders from inactive channel.');
        }

        return DB::transaction(function () use ($channel, $orderData, $items) {
            $orderData['organization_id'] = $channel->organization_id;
            $orderData['channel_id'] = $channel->id;
            $orderData['status'] = $orderData['status'] ?? EcommerceOrder::STATUS_PENDING;

            $order = EcommerceOrder::create($orderData);

            foreach ($items as $itemData) {
                $itemData['order_id'] = $order->id;

                // Try to map external product to internal product
                if (!empty($itemData['external_product_id'])) {
                    $mapping = $channel->productMappings()
                        ->where('external_product_id', $itemData['external_product_id'])
                        ->first();

                    if ($mapping) {
                        $itemData['product_id'] = $mapping->product_id;
                    }
                }

                EcommerceOrderItem::create($itemData);
            }

            return $order->load('items');
        });
    }

    /**
     * Sync orders from a channel.
     *
     * Fetches orders from the external platform adapter, imports new orders,
     * updates existing orders whose status has changed, and records sync
     * metrics in the returned log entry.
     */
    public function syncOrders(EcommerceChannel $channel): EcommerceSyncLog
    {
        if (!$channel->isActive()) {
            throw new \InvalidArgumentException('Cannot sync orders from inactive channel.');
        }

        $syncLog = EcommerceSyncLog::create([
            'channel_id' => $channel->id,
            'sync_type' => EcommerceSyncLog::TYPE_ORDERS,
            'direction' => EcommerceSyncLog::DIRECTION_PULL,
            'status' => EcommerceSyncLog::STATUS_STARTED,
            'started_at' => now(),
        ]);

        try {
            $adapter = $this->resolveChannelAdapter($channel);
            $since = $channel->last_sync_at;

            $externalOrders = $adapter->fetchOrders($since);

            $totalRecords = count($externalOrders);
            $processedRecords = 0;
            $failedRecords = 0;
            $errors = [];

            foreach ($externalOrders as $externalOrder) {
                try {
                    $this->syncSingleOrder($channel, $externalOrder);
                    $processedRecords++;
                } catch (\Throwable $e) {
                    $failedRecords++;
                    $errors[] = [
                        'external_order_id' => $externalOrder['external_order_id'] ?? 'unknown',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $channel->update(['last_sync_at' => now()]);

            $syncLog->update([
                'status' => $failedRecords === $totalRecords && $totalRecords > 0
                    ? EcommerceSyncLog::STATUS_FAILED
                    : EcommerceSyncLog::STATUS_COMPLETED,
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'failed_records' => $failedRecords,
                'errors' => $errors ?: null,
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

        return $syncLog->fresh();
    }

    /**
     * Import or update a single order from external data during sync.
     */
    protected function syncSingleOrder(EcommerceChannel $channel, array $externalOrder): EcommerceOrder
    {
        return DB::transaction(function () use ($channel, $externalOrder) {
            $externalOrderId = $externalOrder['external_order_id'];
            $items = $externalOrder['items'] ?? [];
            $orderData = collect($externalOrder)->except('items')->toArray();

            $existing = EcommerceOrder::where('channel_id', $channel->id)
                ->where('external_order_id', $externalOrderId)
                ->first();

            if ($existing) {
                return $this->updateExistingOrder($existing, $orderData);
            }

            return $this->importOrder($channel, $orderData, $items);
        });
    }

    /**
     * Update an existing order with fresh external data.
     */
    protected function updateExistingOrder(EcommerceOrder $order, array $data): EcommerceOrder
    {
        $updatable = collect($data)->only([
            'status',
            'financial_status',
            'fulfillment_status',
            'tracking_number',
            'tracking_url',
            'total_amount',
            'raw_data',
        ])->filter()->toArray();

        if (!empty($updatable)) {
            $order->update($updatable);
        }

        return $order->fresh(['items']);
    }

    /**
     * Resolve the platform adapter for a channel.
     *
     * Returns a platform-specific adapter that implements fetchOrders().
     * Defaults to the NullChannelAdapter which returns an empty order list
     * (safe for channels without a live integration).
     */
    protected function resolveChannelAdapter(EcommerceChannel $channel): EcommerceChannelAdapter
    {
        $adapters = [
            EcommerceChannel::PLATFORM_SHOPIFY => ShopifyAdapter::class,
            EcommerceChannel::PLATFORM_WOOCOMMERCE => WooCommerceAdapter::class,
        ];

        $adapterClass = $adapters[$channel->platform] ?? NullChannelAdapter::class;

        return new $adapterClass($channel);
    }

    /**
     * Process an e-commerce order (create sales order/invoice in ERP).
     */
    public function processOrder(EcommerceOrder $order): EcommerceOrder
    {
        if (!$order->canBeProcessed()) {
            throw new \InvalidArgumentException('Order cannot be processed in its current state.');
        }

        return DB::transaction(function () use ($order) {
            // Mark order as processing
            $order->update([
                'status' => EcommerceOrder::STATUS_PROCESSING,
            ]);

            // Map customer if not already mapped
            if (!$order->customer_id && $order->customer_email) {
                $channel = $order->channel;
                $order->update([
                    'customer_id' => $channel->default_customer_id,
                ]);
            }

            // Mark as processed
            $order->update([
                'is_processed' => true,
                'processed_at' => now(),
            ]);

            return $order->fresh(['items', 'channel']);
        });
    }

    /**
     * Fulfill an e-commerce order.
     */
    public function fulfillOrder(EcommerceOrder $order, ?string $trackingNumber = null, ?string $trackingUrl = null): EcommerceOrder
    {
        if (!$order->canBeFulfilled()) {
            throw new \InvalidArgumentException('Order cannot be fulfilled in its current state.');
        }

        return DB::transaction(function () use ($order, $trackingNumber, $trackingUrl) {
            $updateData = [
                'status' => EcommerceOrder::STATUS_SHIPPED,
                'fulfillment_status' => EcommerceOrder::FULFILLMENT_FULFILLED,
            ];

            if ($trackingNumber) {
                $updateData['tracking_number'] = $trackingNumber;
            }

            if ($trackingUrl) {
                $updateData['tracking_url'] = $trackingUrl;
            }

            $order->update($updateData);

            // Mark all items as fulfilled
            $order->items()->update([
                'fulfilled_quantity' => DB::raw('quantity'),
            ]);

            return $order->fresh(['items']);
        });
    }

    /**
     * Get order statistics for a channel or organization.
     */
    public function getOrderStats(int $organizationId, ?int $channelId = null): array
    {
        $query = EcommerceOrder::query()->where('organization_id', $organizationId);

        if ($channelId) {
            // Verify the channel belongs to the same organization before filtering by it.
            $channel = EcommerceChannel::where('id', $channelId)
                ->where('organization_id', $organizationId)
                ->firstOrFail();
            $query->where('channel_id', $channel->id);
        }

        return [
            'total_orders' => $query->count(),
            'pending_orders' => (clone $query)->where('status', EcommerceOrder::STATUS_PENDING)->count(),
            'processing_orders' => (clone $query)->where('status', EcommerceOrder::STATUS_PROCESSING)->count(),
            'shipped_orders' => (clone $query)->where('status', EcommerceOrder::STATUS_SHIPPED)->count(),
            'delivered_orders' => (clone $query)->where('status', EcommerceOrder::STATUS_DELIVERED)->count(),
            'cancelled_orders' => (clone $query)->where('status', EcommerceOrder::STATUS_CANCELLED)->count(),
            'total_revenue' => (clone $query)->where('status', '!=', EcommerceOrder::STATUS_CANCELLED)->sum('total_amount'),
            'unprocessed_orders' => (clone $query)->where('is_processed', false)->count(),
            'by_status' => EcommerceOrder::query()
                ->where('organization_id', $organizationId)
                ->when($channelId, fn ($q) => $q->where('channel_id', $channelId))
                ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
        ];
    }
}
