<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * No-op adapter for channels that don't have a live platform integration.
 *
 * Used as the default when the channel's platform is not yet supported or is
 * of type "custom"/"marketplace" where orders are imported manually.
 */
class NullChannelAdapter implements EcommerceChannelAdapter
{
    public function __construct(
        protected readonly EcommerceChannel $channel,
    ) {}

    /** @inheritDoc */
    public function fetchOrders(?Carbon $since = null): array
    {
        Log::info('NullChannelAdapter: fetchOrders called (no external integration)', [
            'channel_id' => $this->channel->id,
            'platform' => $this->channel->platform,
        ]);

        return [];
    }

    /** @inheritDoc */
    public function testConnection(): bool
    {
        // A null adapter always reports a healthy connection so that
        // channels without external APIs can still be "connected".
        return true;
    }

    /** @inheritDoc */
    public function pushFulfillment(string $externalOrderId, ?string $trackingNumber = null, ?string $trackingUrl = null): bool
    {
        Log::info('NullChannelAdapter: pushFulfillment called (no external integration)', [
            'channel_id' => $this->channel->id,
            'external_order_id' => $externalOrderId,
        ]);

        return true;
    }
}
