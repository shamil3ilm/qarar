<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooCommerce REST API adapter.
 *
 * Uses the WooCommerce REST API v3 with basic auth (consumer key/secret).
 * No external SDK is required.
 *
 * Expected credentials format in the channel:
 *   ['consumer_key' => '...', 'consumer_secret' => '...']
 */
class WooCommerceAdapter implements EcommerceChannelAdapter
{
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;

    public function __construct(
        protected readonly EcommerceChannel $channel,
    ) {
        $credentials = $channel->credentials ?? [];
        $this->consumerKey = $credentials['consumer_key'] ?? '';
        $this->consumerSecret = $credentials['consumer_secret'] ?? '';

        $storeUrl = rtrim($channel->store_url ?? '', '/');
        $this->baseUrl = "{$storeUrl}/wp-json/wc/v3";
    }

    /** @inheritDoc */
    public function fetchOrders(?Carbon $since = null): array
    {
        if (empty($this->consumerKey) || empty($this->consumerSecret) || empty($this->channel->store_url)) {
            Log::warning('WooCommerceAdapter: missing credentials or store URL', [
                'channel_id' => $this->channel->id,
            ]);
            return [];
        }

        $params = [
            'per_page' => 100,
            'orderby' => 'date',
            'order' => 'desc',
        ];

        if ($since) {
            $params['after'] = $since->toIso8601String();
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(30)
                ->get("{$this->baseUrl}/orders", $params);

            if (!$response->successful()) {
                Log::error('WooCommerceAdapter: failed to fetch orders', [
                    'channel_id' => $this->channel->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $wooOrders = $response->json() ?? [];

            return array_map(fn (array $order) => $this->mapOrder($order), $wooOrders);
        } catch (\Throwable $e) {
            Log::error('WooCommerceAdapter: exception fetching orders', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** @inheritDoc */
    public function testConnection(): bool
    {
        if (empty($this->consumerKey) || empty($this->consumerSecret) || empty($this->channel->store_url)) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(10)
                ->get("{$this->baseUrl}/system_status");

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
                'status' => 'completed',
            ];

            if ($trackingNumber) {
                $payload['meta_data'] = [
                    ['key' => '_tracking_number', 'value' => $trackingNumber],
                    ['key' => '_tracking_url', 'value' => $trackingUrl ?? ''],
                ];
            }

            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(15)
                ->put("{$this->baseUrl}/orders/{$externalOrderId}", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('WooCommerceAdapter: failed to push fulfillment', [
                'channel_id' => $this->channel->id,
                'external_order_id' => $externalOrderId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Map a WooCommerce order response to the internal order format.
     */
    protected function mapOrder(array $wooOrder): array
    {
        $items = array_map(fn (array $item) => [
            'external_product_id' => (string) ($item['product_id'] ?? ''),
            'external_variant_id' => (string) ($item['variation_id'] ?? ''),
            'sku' => $item['sku'] ?? null,
            'name' => $item['name'] ?? '',
            'quantity' => (int) ($item['quantity'] ?? 1),
            'unit_price' => (string) ($item['price'] ?? '0.00'),
            'discount_amount' => '0.00',
            'tax_amount' => (string) ($item['total_tax'] ?? '0.00'),
            'total_amount' => (string) ($item['total'] ?? '0.00'),
        ], $wooOrder['line_items'] ?? []);

        $shippingAddress = null;
        if (!empty($wooOrder['shipping'])) {
            $addr = $wooOrder['shipping'];
            $shippingAddress = [
                'name' => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
                'address1' => $addr['address_1'] ?? null,
                'address2' => $addr['address_2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['state'] ?? null,
                'country' => $addr['country'] ?? null,
                'zip' => $addr['postcode'] ?? null,
                'phone' => $addr['phone'] ?? null,
            ];
        }

        $billingAddress = null;
        if (!empty($wooOrder['billing'])) {
            $addr = $wooOrder['billing'];
            $billingAddress = [
                'name' => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
                'address1' => $addr['address_1'] ?? null,
                'address2' => $addr['address_2'] ?? null,
                'city' => $addr['city'] ?? null,
                'province' => $addr['state'] ?? null,
                'country' => $addr['country'] ?? null,
                'zip' => $addr['postcode'] ?? null,
                'phone' => $addr['phone'] ?? null,
            ];
        }

        return [
            'external_order_id' => (string) $wooOrder['id'],
            'order_number' => (string) ($wooOrder['number'] ?? $wooOrder['id']),
            'status' => $this->mapWooStatus($wooOrder['status'] ?? 'pending'),
            'financial_status' => $this->mapFinancialStatus($wooOrder['status'] ?? 'pending'),
            'fulfillment_status' => $this->mapFulfillmentStatus($wooOrder['status'] ?? 'pending'),
            'customer_email' => $wooOrder['billing']['email'] ?? null,
            'customer_name' => trim(
                ($wooOrder['billing']['first_name'] ?? '') . ' ' . ($wooOrder['billing']['last_name'] ?? '')
            ),
            'customer_phone' => $wooOrder['billing']['phone'] ?? null,
            'currency_code' => $wooOrder['currency'] ?? 'USD',
            'subtotal' => (string) collect($wooOrder['line_items'] ?? [])->sum('subtotal'),
            'discount_amount' => (string) ($wooOrder['discount_total'] ?? '0.00'),
            'shipping_amount' => (string) ($wooOrder['shipping_total'] ?? '0.00'),
            'tax_amount' => (string) ($wooOrder['total_tax'] ?? '0.00'),
            'total_amount' => (string) ($wooOrder['total'] ?? '0.00'),
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'ordered_at' => $wooOrder['date_created'] ?? now()->toIso8601String(),
            'raw_data' => $wooOrder,
            'items' => $items,
        ];
    }

    protected function mapWooStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'delivered',
            'processing', 'on-hold' => 'processing',
            'cancelled', 'failed' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function mapFinancialStatus(string $status): string
    {
        return match ($status) {
            'completed', 'processing' => 'paid',
            'refunded' => 'refunded',
            'on-hold' => 'pending',
            default => 'pending',
        };
    }

    protected function mapFulfillmentStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'fulfilled',
            default => 'unfulfilled',
        };
    }
}
