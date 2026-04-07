<?php

declare(strict_types=1);

namespace App\Services\Core;

use Illuminate\Support\Facades\Log;

/**
 * Logs structured JSON records for critical financial operations to a dedicated
 * channel, making outcomes auditable and queryable outside the general log stream.
 */
class FinancialOperationLogger
{
    public const OUTCOME_SUCCESS  = 'success';
    public const OUTCOME_FAILURE  = 'failure';
    public const OUTCOME_CONFLICT = 'conflict';

    private const CHANNEL = 'financial_operations';

    /**
     * Record the result of a financial operation.
     *
     * @param string         $operation   Logical operation name (e.g. "invoice.post")
     * @param string         $entityType  Domain entity type (e.g. "invoice", "payroll_run")
     * @param int|string     $entityId    Primary key / UUID of the entity
     * @param int            $orgId       Tenant organisation ID
     * @param string         $outcome     One of: 'success', 'failure', 'conflict'
     * @param float          $durationMs  Wall-clock duration of the operation in milliseconds
     * @param array<string, mixed> $context     Arbitrary extra data to attach to the log entry
     */
    public function log(
        string $operation,
        string $entityType,
        int|string $entityId,
        int $orgId,
        string $outcome,
        float $durationMs,
        array $context = [],
    ): void {
        $payload = [
            'operation'   => $operation,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'org_id'      => $orgId,
            'user_id'     => auth()->id(),
            'duration_ms' => $durationMs,
            'outcome'     => $outcome,
            'context'     => $context,
            'timestamp'   => now()->toIso8601String(),
        ];

        $channel = Log::channel(self::CHANNEL);

        match ($outcome) {
            self::OUTCOME_SUCCESS  => $channel->info('financial_operation', $payload),
            self::OUTCOME_CONFLICT => $channel->warning('financial_operation', $payload),
            default                => $channel->error('financial_operation', $payload),
        };
    }
}
