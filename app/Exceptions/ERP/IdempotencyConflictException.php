<?php

declare(strict_types=1);

namespace App\Exceptions\ERP;

/**
 * Thrown when a financial operation is already in progress for the same idempotency key.
 *
 * HTTP 409 Conflict. The caller should retry after a short delay.
 */
class IdempotencyConflictException extends ErpException
{
    protected string $errorCode = 'IDEMPOTENCY_CONFLICT';
    protected int $httpStatus   = 409;

    public static function forOperation(string $key, string $operation): self
    {
        return new self(
            "Operation '{$operation}' is already in progress for key '{$key}'. " .
            'Retry after a short delay.',
            ['key' => $key, 'operation' => $operation],
        );
    }
}
