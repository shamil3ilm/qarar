<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Adds structured (JSON-context) logging to any service class.
 *
 * Usage in a service:
 *   use StructuredLogger;
 *   $this->logInfo('Invoice sent', ['invoice_id' => $id, 'total' => $total]);
 */
trait StructuredLogger
{
    private function logContext(): array
    {
        return [
            'service' => static::class,
            'pid'     => getmypid(),
        ];
    }

    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge($this->logContext(), $context));
    }

    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($this->logContext(), $context));
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge($this->logContext(), $context));
    }

    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug($message, array_merge($this->logContext(), $context));
    }

    /**
     * Time a callable and log its duration.
     */
    protected function logTimed(string $operation, callable $callback, array $context = []): mixed
    {
        $start = microtime(true);
        try {
            $result = $callback();
            $this->logInfo("{$operation} completed", array_merge($context, [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]));
            return $result;
        } catch (\Throwable $e) {
            $this->logError("{$operation} failed", array_merge($context, [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'exception'   => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]));
            throw $e;
        }
    }
}
