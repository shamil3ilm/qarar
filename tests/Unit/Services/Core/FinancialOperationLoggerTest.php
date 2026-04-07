<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Core;

use App\Services\Core\FinancialOperationLogger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FinancialOperationLoggerTest extends TestCase
{
    private FinancialOperationLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new FinancialOperationLogger();
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * Call log() with all required parameters and sensible defaults.
     *
     * @param array<string, mixed> $context
     */
    private function callLog(
        string $outcome,
        string $operation = 'invoice.post',
        string $entityType = 'invoice',
        int|string $entityId = 42,
        int $orgId = 1,
        float $durationMs = 123.4,
        array $context = [],
    ): void {
        $this->logger->log($operation, $entityType, $entityId, $orgId, $outcome, $durationMs, $context);
    }

    // ==========================================
    // outcome = 'success'
    // ==========================================

    public function test_success_outcome_calls_info_with_correct_payload_fields(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelMock);

        $captured = [];
        $channelMock->shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use (&$captured): bool {
                $captured = $context;
                return $message === 'financial_operation';
            });

        $this->logger->log(
            operation: 'invoice.post',
            entityType: 'invoice',
            entityId: 99,
            orgId: 5,
            outcome: FinancialOperationLogger::OUTCOME_SUCCESS,
            durationMs: 55.5,
            context: ['test' => true],
        );

        $this->assertSame('invoice.post', $captured['operation']);
        $this->assertSame('invoice',      $captured['entity_type']);
        $this->assertSame(99,             $captured['entity_id']);
        $this->assertSame(5,              $captured['org_id']);
        $this->assertSame(FinancialOperationLogger::OUTCOME_SUCCESS, $captured['outcome']);
        $this->assertSame(55.5,           $captured['duration_ms']);
        $this->assertArrayHasKey('timestamp', $captured);
        $this->assertArrayHasKey('user_id',   $captured);
        $this->assertTrue($captured['context']['test']);
    }

    // ==========================================
    // outcome = 'failure'
    // ==========================================

    public function test_failure_outcome_calls_error_on_channel(): void
    {
        $channelSpy = \Mockery::spy(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelSpy);

        $this->callLog(FinancialOperationLogger::OUTCOME_FAILURE);

        $channelSpy->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $msg) => $msg === 'financial_operation');
    }

    public function test_failure_outcome_payload_contains_correct_outcome_field(): void
    {
        $channelSpy = \Mockery::spy(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelSpy);

        $this->callLog(FinancialOperationLogger::OUTCOME_FAILURE);

        $channelSpy->shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $context['outcome'] === FinancialOperationLogger::OUTCOME_FAILURE;
            });
    }

    // ==========================================
    // outcome = 'conflict'
    // ==========================================

    public function test_conflict_outcome_calls_warning_on_channel(): void
    {
        $channelSpy = \Mockery::spy(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelSpy);

        $this->callLog(FinancialOperationLogger::OUTCOME_CONFLICT);

        $channelSpy->shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $msg) => $msg === 'financial_operation');
    }

    public function test_conflict_outcome_payload_contains_correct_outcome_field(): void
    {
        $channelSpy = \Mockery::spy(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelSpy);

        $this->callLog(FinancialOperationLogger::OUTCOME_CONFLICT);

        $channelSpy->shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $context['outcome'] === FinancialOperationLogger::OUTCOME_CONFLICT;
            });
    }

    // ==========================================
    // Unknown / arbitrary outcome falls to error
    // ==========================================

    public function test_unknown_outcome_falls_through_to_error_level(): void
    {
        $channelSpy = \Mockery::spy(\Psr\Log\LoggerInterface::class);

        Log::shouldReceive('channel')
            ->with('financial_operations')
            ->andReturn($channelSpy);

        $this->callLog('unknown_outcome');

        $channelSpy->shouldHaveReceived('error')->once();
        $channelSpy->shouldNotHaveReceived('info');
        $channelSpy->shouldNotHaveReceived('warning');
    }
}
