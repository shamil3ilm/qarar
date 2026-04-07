<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Jobs\TrackUserEvent;
use App\Services\Campaign\CampaignService;
use Illuminate\Http\Request;

class UserEventService
{
    /**
     * Dispatch an async job to record a user event.
     *
     * Keeping the dispatch asynchronous ensures that event tracking never
     * adds latency to the request that triggered it.
     */
    public function track(
        string $eventType,
        array $payload = [],
        ?int $userId = null,
        ?int $organizationId = null,
        ?Request $request = null
    ): void {
        TrackUserEvent::dispatch(
            $eventType,
            $payload,
            $userId,
            $organizationId,
            $request?->ip(),
            $request?->userAgent(),
        )->afterCommit();

        if ($organizationId !== null) {
            CampaignService::triggerEventAsync(
                $eventType,
                $userId ?? 0,
                $organizationId,
            );
        }
    }
}
