<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Core;

use App\Exceptions\ERP\IdempotencyConflictException;
use App\Services\Core\FinancialIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FinancialIdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialIdempotencyService $service;

    private const KEY       = 'invoice:42:send';
    private const OPERATION = 'invoice.send';
    private const ORG_ID    = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinancialIdempotencyService();
    }

    /**
     * The first call executes the callback and returns its result.
     */
    public function test_first_call_executes_callback_and_returns_result(): void
    {
        $result = $this->service->execute(
            self::KEY,
            self::OPERATION,
            self::ORG_ID,
            fn () => ['status' => 'sent', 'invoice_id' => 42],
        );

        $this->assertEquals(['status' => 'sent', 'invoice_id' => 42], $result);

        $row = DB::table('financial_idempotency_keys')
            ->where('key', self::KEY)
            ->where('operation', self::OPERATION)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals('completed', $row->status);
    }

    /**
     * A second call with the same key returns the cached result without re-running the callback.
     */
    public function test_second_call_with_same_key_returns_cached_result_without_executing_callback(): void
    {
        $callCount = 0;

        $callback = function () use (&$callCount): array {
            $callCount++;
            return ['order_id' => 99];
        };

        // First call — executes callback
        $first = $this->service->execute(self::KEY, self::OPERATION, self::ORG_ID, $callback);

        // Second call — must NOT execute the callback again
        $second = $this->service->execute(self::KEY, self::OPERATION, self::ORG_ID, $callback);

        $this->assertEquals(1, $callCount, 'Callback should only be invoked once');
        $this->assertEquals($first, $second);
    }

    /**
     * A non-stale "processing" row (concurrent execution) throws IdempotencyConflictException.
     */
    public function test_concurrent_processing_row_throws_idempotency_conflict_exception(): void
    {
        // Manually insert a fresh "processing" row (expires in the future, created just now)
        DB::table('financial_idempotency_keys')->insert([
            'key'             => self::KEY,
            'operation'       => self::OPERATION,
            'organization_id' => self::ORG_ID,
            'status'          => 'processing',
            'expires_at'      => now()->addMinutes(60),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->expectException(IdempotencyConflictException::class);

        $this->service->execute(
            self::KEY,
            self::OPERATION,
            self::ORG_ID,
            fn () => ['result' => 'should not run'],
        );
    }

    /**
     * A failing callback deletes the row so the caller can retry.
     */
    public function test_failed_callback_does_not_cache_result_and_allows_retry(): void
    {
        $attemptCount = 0;

        // First call — callback throws
        try {
            $this->service->execute(
                self::KEY,
                self::OPERATION,
                self::ORG_ID,
                function () use (&$attemptCount): never {
                    $attemptCount++;
                    throw new RuntimeException('Payment gateway timeout');
                },
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Payment gateway timeout', $e->getMessage());
        }

        $this->assertDatabaseMissing('financial_idempotency_keys', [
            'key'       => self::KEY,
            'operation' => self::OPERATION,
        ]);

        // Second call — callback succeeds
        $result = $this->service->execute(
            self::KEY,
            self::OPERATION,
            self::ORG_ID,
            fn () => ['retry' => true],
        );

        $this->assertEquals(['retry' => true], $result);
        $this->assertEquals(1, $attemptCount);
    }

    /**
     * An expired completed row allows fresh re-execution.
     */
    public function test_expired_key_allows_re_execution(): void
    {
        // Insert a completed but expired row
        DB::table('financial_idempotency_keys')->insert([
            'key'              => self::KEY,
            'operation'        => self::OPERATION,
            'organization_id'  => self::ORG_ID,
            'status'           => 'completed',
            'response_payload' => json_encode(['old' => 'result']),
            'expires_at'       => now()->subMinutes(5),
            'created_at'       => now()->subHours(25),
            'updated_at'       => now()->subHours(25),
        ]);

        $callCount = 0;

        $result = $this->service->execute(
            self::KEY,
            self::OPERATION,
            self::ORG_ID,
            function () use (&$callCount): array {
                $callCount++;
                return ['fresh' => 'execution'];
            },
        );

        $this->assertEquals(['fresh' => 'execution'], $result);
        $this->assertEquals(1, $callCount, 'Callback should have been executed for the expired key');
    }

    /**
     * cleanup() removes all expired rows and leaves valid rows intact.
     */
    public function test_cleanup_removes_expired_rows(): void
    {
        $now = now();

        // Two expired rows
        DB::table('financial_idempotency_keys')->insert([
            [
                'key'             => 'expired-key-1',
                'operation'       => 'op.one',
                'organization_id' => self::ORG_ID,
                'status'          => 'completed',
                'expires_at'      => $now->copy()->subHour(),
                'created_at'      => $now->copy()->subDay(),
                'updated_at'      => $now->copy()->subDay(),
            ],
            [
                'key'             => 'expired-key-2',
                'operation'       => 'op.two',
                'organization_id' => self::ORG_ID,
                'status'          => 'completed',
                'expires_at'      => $now->copy()->subMinutes(10),
                'created_at'      => $now->copy()->subDay(),
                'updated_at'      => $now->copy()->subDay(),
            ],
            // One valid row (expires in the future)
            [
                'key'             => 'valid-key',
                'operation'       => 'op.three',
                'organization_id' => self::ORG_ID,
                'status'          => 'completed',
                'expires_at'      => $now->copy()->addHours(23),
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ]);

        $deleted = $this->service->cleanup();

        $this->assertEquals(2, $deleted);

        $this->assertDatabaseMissing('financial_idempotency_keys', ['key' => 'expired-key-1']);
        $this->assertDatabaseMissing('financial_idempotency_keys', ['key' => 'expired-key-2']);
        $this->assertDatabaseHas('financial_idempotency_keys', ['key' => 'valid-key']);
    }
}
