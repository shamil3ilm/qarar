<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopify REST Admin API adapter.
 *
 * Communicates with the Shopify Admin API using the channel's stored
 * credentials (api_key / access_token).  No external SDK is required.
 *
 * Expected credentials format in the channel:
 *   ['access_token' => '...', 'api_version' => '2024-01']  (api_version is optional)
 */
class ShopifyAdapter implements EcommerceChannelAdapter
{
    protected string $baseUrl;
    protected string $accessToken;
    protected string $apiVersion;

    public function __construct(
        protected readonly EcommerceChannel $channel,
    ) {
        $credentials = $channel->credentials ?? [];
        $this->accessToken = $credentials['access_token'] ?? '';
        $this->apiVersion = $credentials['api_version'] ?? '2024-01';

        $storeUrl = rtrim($channel->store_url ?? '', '/');
        $this->baseUrl = "{$storeUrl}/admin/api/{$this->apiVersion}";
    }

    /** @inheritDoc */
    public function fetchOrders(?Carbon $since = null): array
    {
        if (empty($this->accessToken) || empty($this->channel->store_url)) {
            Log::warning('ShopifyAdapter: missing credentials or store URL', [
                'channel_id' => $this->channel->id,
            ]);
            return [];
        }

        $params = [
            'status' => 'any',
            'limit' => 250,
        ];

        if ($since) {
            $params['updated_at_min'] = $since->toIso8601String();
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$this->baseUrl}/orders.json", $params);

            if (!$response->successful()) {
                Log::error('ShopifyAdapter: failed to fetch orders', [
                    'channel_id' => $this->channel->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $shopifyOrders = $response->json('orders', []);

            return array_map(fn (array $order) => $this->mapOrder($order), $shopifyOrders);
        } catch (\Throwable $e) {
            Log::error('ShopifyAdapter: exception fetching orders', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** @inheritDoc */
    public function testConnection(): bool
    {
        if (empty($this->accessToken) || empty($this->channel->store_url)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->timeout(10)->get("{$this->baseUrl}/shop.json");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @inheritDoc */
    public function pushFulfillment(string $externalOrderId, ?string $trackingNumber = null, ?string $trackingUrl = null): bool
    {
        try {
            $payload = [
                'fulfillment' => [
                    'tracking_number' => $trackingNumber,
                    'tracking_url' => $trackingUrl,
                    'notify_customer' => true,
                ],
            ];

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(
                "{$this->baseUrl}/orders/{$externalOrderId}/fulfillments.json",
                $payload
            );

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('ShopifyAdapter: failed to push fulfillment', [
                'channel_id' => $this->channel->id,
                'external_order_id' => $externalOrderId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Map a Shopify order response to the internal order format.
     */
    protected function mapOrder(array $shopifyOrder): array
    {
        $items = array_map(fn (array $item) => [
            'external_product_id' => (string) ($item['product_id'] ?? ''),
            'external_variant_id' => (string) ($item['variant_id'] ?? ''),
            'sku' => $item['sku'] ?? null,
            'name' => $item['title'] ?? $item['name'] ?? '',
            'quantity' => (int) ($item['quantity'] ?? 1),
            'unit_price' => (string) ($item['price'] ?? '0.00'),
            'discount_amount' => (string) ($item['total_discount'] ?? '0.00'),
            'tax_amount' => (string) collect($item['tax_lines'] ?? [])->sum('price'),
            'total_amount' => (string) bcmul((string) ($item['price'] ?? '0'), (string) ($item['quantity'] ?? 1), 2),
        ], $shopifyOrder['line_items'] ?? []);

        $shippingAddress = null;
        if (!empty($shopifyOrder['shipping_address'])) {
            $addr = $shopifyOrder['shipping_address'];
            $shippingAddress = [
                'name' => $addr['name'] ?? null,
                'address1' => $addr['address1'] ?? null,
                'address2' => $addr['address2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['province'] ?? null,
                'country' => $addr['country'] ?? null,
                'zip' => $addr['zip'] ?? null,
                'phone' => $addr['phone'] ?? null,
            ];
        }

        $billingAddress = null;
        if (!empty($shopifyOrder['billing_address'])) {
            $addr = $shopifyOrder['billing_address'];
            $billingAddress = [
                'name' => $addr['name'] ?? null,
                'address1' => $addr['address1'] ?? null,
                'address2' => $addr['address2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['province'] ?? null,
                'country' => $addr['country'] ?? null,
                'zip' => $addr['zip'] ?? null,
                'phone' => $addr['phone'] ?? null,
            ];
        }

        return [
            'external_order_id' => (string) $shopifyOrder['id'],
            'order_number' => (string) ($shopifyOrder['order_number'] ?? $shopifyOrder['name'] ?? ''),
            'status' => $this->mapShopifyStatus($shopifyOrder),
            'financial_status' => $this->mapFinancialStatus($shopifyOrder['financial_status'] ?? 'pending'),
            'fulfillment_status' => $this->mapFulfillmentStatus($shopifyOrder['fulfillment_status'] ?? null),
            'customer_email' => $shopifyOrder['email'] ?? $shopifyOrder['contact_email'] ?? null,
            'customer_name' => trim(($shopifyOrder['customer']['first_name'] ?? '') . ' ' . ($shopifyOrder['customer']['last_name'] ?? '')),
            'customer_phone' => $shopifyOrder['phone'] ?? null,
            'currency_code' => $shopifyOrder['currency'] ?? 'USD',
            'subtotal' => (string) ($shopifyOrder['subtotal_price'] ?? '0.00'),
            'discount_amount' => (string) ($shopifyOrder['total_discounts'] ?? '0.00'),
            'shipping_amount' => (string) collect($shopifyOrder['shipping_lines'] ?? [])->sum('price'),
            'tax_amount' => (string) ($shopifyOrder['total_tax'] ?? '0.00'),
            'total_amount' => (string) ($shopifyOrder['total_price'] ?? '0.00'),
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'ordered_at' => $shopifyOrder['created_at'] ?? now()->toIso8601String(),
            'raw_data' => $shopifyOrder,
            'items' => $items,
        ];
    }

    protected function mapShopifyStatus(array $order): string
    {
        if (($order['cancelled_at'] ?? null) !== null) {
            return 'cancelled';
        }

        return match ($order['fulfillment_status'] ?? null) {
            'fulfilled' => 'shipped',
            'partial' => 'processing',
            default => 'pending',
        };
    }

    protected function mapFinancialStatus(string $status): string
    {
        return match ($status) {
            'paid', 'partially_paid' => $status,
            'refunded', 'partially_refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function mapFulfillmentStatus(?string $status): string
    {
        return match ($status) {
            'fulfilled' => 'fulfilled',
            'partial' => 'partial',
            default => 'unfulfilled',
        };
    }
}
