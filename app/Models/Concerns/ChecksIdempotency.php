<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Core\FinancialIdempotencyService;

/**
 * Convenience trait for service classes that perform critical financial writes.
 *
 * Resolves FinancialIdempotencyService from the container so that services do not
 * need to declare it as a constructor dependency just for idempotency.
 */
trait ChecksIdempotency
{
    /**
     * Execute a financial operation under idempotency protection.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withFinancialIdempotency(
        string $key,
        string $operation,
        int $orgId,
        callable $callback,
        int $ttlMinutes = 1440,
    ): mixed {
        return app(FinancialIdempotencyService::class)->execute(
            key: $key,
            operation: $operation,
            orgId: $orgId,
            callback: $callback,
            ttlMinutes: $ttlMinutes,
        );
    }
}
