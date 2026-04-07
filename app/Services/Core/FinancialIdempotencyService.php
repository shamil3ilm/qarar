<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Exceptions\ERP\IdempotencyConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed idempotency for critical financial operations.
 *
 * Uses a unique-constraint INSERT as the serialisation point — the database,
 * not application code, guarantees exactly-once semantics under concurrency.
 *
 * Distinct from App\Services\Security\IdempotencyService, which is HTTP-layer
 * request deduplication (stores JsonResponse objects). This service operates
 * at the domain/service layer and stores operation results as plain data.
 */
class FinancialIdempotencyService
{
    private const DEFAULT_TTL_MINUTES = 1440; // 24 hours
    private const PROCESSING_STALE_MINUTES = 10;

    /**
     * Execute a callback under idempotency protection.
     *
     * - First call: inserts a 'processing' row, runs callback, stores result, returns result.
     * - Duplicate call (completed, within TTL): returns cached result without re-running.
     * - Concurrent call (processing): throws IdempotencyConflictException (HTTP 409).
     * - Failed/stale call: deletes the row and allows a fresh retry.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws IdempotencyConflictException
     */
    public function execute(
        string $key,
        string $operation,
        int $orgId,
        callable $callback,
        int $ttlMinutes = self::DEFAULT_TTL_MINUTES,
    ): mixed {
        $expiresAt = now()->addMinutes($ttlMinutes);

        // --- Attempt first-writer INSERT ---
        try {
            DB::table('financial_idempotency_keys')->insert([
                'key'             => $key,
                'operation'       => $operation,
                'organization_id' => $orgId,
                'status'          => 'processing',
                'expires_at'      => $expiresAt,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (QueryException $e) {
            // Unique constraint violation — a row already exists for this key+operation+org.
            if (!$this->isIntegrityViolation($e)) {
                throw $e;
            }

            return $this->handleDuplicate($key, $operation, $orgId, $callback, $ttlMinutes);
        }

        // --- We own the row: run the operation ---
        try {
            $result = $callback();

            DB::table('financial_idempotency_keys')
                ->where('key', $key)
                ->where('operation', $operation)
                ->where('organization_id', $orgId)
                ->update([
                    'status'           => 'completed',
                    'response_payload' => json_encode($result, JSON_THROW_ON_ERROR),
                    'updated_at'       => now(),
                ]);

            return $result;
        } catch (\Throwable $e) {
            // Operation failed — delete the row so the caller can retry.
            DB::table('financial_idempotency_keys')
                ->where('key', $key)
                ->where('operation', $operation)
                ->where('organization_id', $orgId)
                ->delete();

            throw $e;
        }
    }

    /**
     * Remove all expired idempotency keys.  Run daily via scheduler.
     */
    public function cleanup(): int
    {
        return DB::table('financial_idempotency_keys')
            ->where('expires_at', '<', now())
            ->delete();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function handleDuplicate(
        string $key,
        string $operation,
        int $orgId,
        callable $callback,
        int $ttlMinutes,
    ): mixed {
        $row = DB::table('financial_idempotency_keys')
            ->where('key', $key)
            ->where('operation', $operation)
            ->where('organization_id', $orgId)
            ->first();

        if (!$row) {
            // Row disappeared between the failed INSERT and this SELECT (race + immediate delete).
            // Retry the whole execute() flow once.
            return $this->execute($key, $operation, $orgId, $callback, $ttlMinutes);
        }

        // Expired row — delete and allow fresh execution.
        if ($row->expires_at !== null && now()->gt($row->expires_at)) {
            DB::table('financial_idempotency_keys')
                ->where('key', $key)
                ->where('operation', $operation)
                ->where('organization_id', $orgId)
                ->delete();

            return $this->execute($key, $operation, $orgId, $callback, $ttlMinutes);
        }

        // Failed row — allow retry.
        if ($row->status === 'failed') {
            DB::table('financial_idempotency_keys')
                ->where('key', $key)
                ->where('operation', $operation)
                ->where('organization_id', $orgId)
                ->delete();

            return $this->execute($key, $operation, $orgId, $callback, $ttlMinutes);
        }

        // Stale processing row (worker crashed) — allow retry.
        if ($row->status === 'processing') {
            $staleBefore = now()->subMinutes(self::PROCESSING_STALE_MINUTES);

            if ($row->created_at !== null && $row->created_at < $staleBefore) {
                DB::table('financial_idempotency_keys')
                    ->where('key', $key)
                    ->where('operation', $operation)
                    ->where('organization_id', $orgId)
                    ->delete();

                return $this->execute($key, $operation, $orgId, $callback, $ttlMinutes);
            }

            throw IdempotencyConflictException::forOperation($key, $operation);
        }

        // Completed row within TTL — return cached result.
        if ($row->status === 'completed' && $row->response_payload !== null) {
            return json_decode($row->response_payload, true, 512, JSON_THROW_ON_ERROR);
        }

        // Fallback: something unexpected — allow re-execution.
        return $callback();
    }

    private function isIntegrityViolation(QueryException $e): bool
    {
        // MySQL: 1062, SQLite: 19, PostgreSQL: 23505
        $code = (int) $e->errorInfo[1];

        return in_array($code, [1062, 19, 23505], true)
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'duplicate key');
    }
}
