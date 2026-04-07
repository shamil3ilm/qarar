<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use Carbon\Carbon;

/**
 * Contract for e-commerce platform adapters.
 *
 * Each supported platform (Shopify, WooCommerce, etc.) should provide an
 * implementation that knows how to communicate with the external API.
 *
 * All methods receive and return plain arrays so the caller remains
 * decoupled from platform-specific SDKs.
 */
interface EcommerceChannelAdapter
{
    /**
     * Fetch orders from the external platform.
     *
     * @param  Carbon|null  $since  Only fetch orders created/updated after this timestamp.
     * @return array<int, array{
     *     external_order_id: string,
     *     order_number: string,
     *     status: string,
     *     financial_status?: string,
     *     fulfillment_status?: string,
     *     customer_email?: string,
     *     customer_name?: string,
     *     customer_phone?: string,
     *     currency_code: string,
     *     subtotal: numeric-string,
     *     discount_amount?: numeric-string,
     *     shipping_amount?: numeric-string,
     *     tax_amount?: numeric-string,
     *     total_amount: numeric-string,
     *     shipping_address?: array,
     *     billing_address?: array,
     *     ordered_at: string,
     *     raw_data?: array,
     *     items: array<int, array{
     *         external_product_id?: string,
     *         external_variant_id?: string,
     *         sku?: string,
     *         name: string,
     *         quantity: int,
     *         unit_price: numeric-string,
     *         discount_amount?: numeric-string,
     *         tax_amount?: numeric-string,
     *         total_amount: numeric-string,
     *     }>,
     * }>
     */
    public function fetchOrders(?Carbon $since = null): array;

    /**
     * Test the connection credentials.
     *
     * @return bool True when the channel credentials are valid and the
     *              external platform is reachable.
     */
    public function testConnection(): bool;

    /**
     * Push a fulfillment update to the external platform.
     *
     * @param  string       $externalOrderId  The platform's order identifier.
     * @param  string|null  $trackingNumber
     * @param  string|null  $trackingUrl
     * @return bool True on success.
     */
    public function pushFulfillment(string $externalOrderId, ?string $trackingNumber = null, ?string $trackingUrl = null): bool;
}
