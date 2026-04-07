<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Simple Redis-backed circuit breaker for external API calls.
 *
 * States:
 *   CLOSED    — normal operation
 *   OPEN      — failures exceeded threshold; fast-fail for $openTtl seconds
 *   HALF-OPEN — single probe request allowed after open TTL expires
 *
 * Usage:
 *   if ($breaker->isOpen('zatca')) throw new \RuntimeException('Circuit open');
 *   try {
 *       $result = $apiCall();
 *       $breaker->recordSuccess('zatca');
 *   } catch (\Throwable $e) {
 *       $breaker->recordFailure('zatca');
 *       throw $e;
 *   }
 */
class CircuitBreaker
{
    private const FAILURE_COUNT_KEY = 'circuit_breaker:failures:';
    private const OPEN_KEY          = 'circuit_breaker:open:';

    public function __construct(
        private readonly int $failureThreshold = 5,   // failures before opening
        private readonly int $openTtlSeconds   = 60,  // how long to stay open
        private readonly int $counterTtl       = 120, // failure counter TTL
    ) {}

    public function isOpen(string $service): bool
    {
        return (bool) Cache::get(self::OPEN_KEY . $service, false);
    }

    public function recordSuccess(string $service): void
    {
        Cache::forget(self::FAILURE_COUNT_KEY . $service);
        Cache::forget(self::OPEN_KEY . $service);
    }

    public function recordFailure(string $service): void
    {
        $key   = self::FAILURE_COUNT_KEY . $service;
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, $this->counterTtl);

        if ($count >= $this->failureThreshold) {
            Cache::put(self::OPEN_KEY . $service, true, $this->openTtlSeconds);
            Log::warning('CircuitBreaker: circuit opened', [
                'service'          => $service,
                'failure_count'    => $count,
                'open_for_seconds' => $this->openTtlSeconds,
            ]);
        }
    }

    public function getState(string $service): string
    {
        if ($this->isOpen($service)) {
            return 'open';
        }

        $count = (int) Cache::get(self::FAILURE_COUNT_KEY . $service, 0);

        return $count > 0 ? 'half-open' : 'closed';
    }
}
