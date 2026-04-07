<?php

declare(strict_types=1);

namespace Tests\Unit\Models\StateMachine;

use App\Exceptions\ERP\InvalidStateTransitionException;
use App\Models\HR\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PayrollPeriodStateTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        // Create a PayrollPeriod bypassing the tenant guard (no auth needed for unit tests)
        $this->period = PayrollPeriod::withoutTenantCheck(function () {
            return PayrollPeriod::factory()->create([
                'organization_id' => $this->organization->id,
                'status'          => PayrollPeriod::STATUS_OPEN,
            ]);
        }, reason: 'unit test fixture');
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    public function test_valid_transition_open_to_processing_succeeds(): void
    {
        $this->assertEquals(PayrollPeriod::STATUS_OPEN, $this->period->getState());

        $this->period->transitionTo(PayrollPeriod::STATUS_PROCESSING);

        $this->period->refresh();
        $this->assertEquals(PayrollPeriod::STATUS_PROCESSING, $this->period->getState());
    }

    public function test_valid_transition_processing_to_processed_succeeds(): void
    {
        PayrollPeriod::withoutTenantCheck(fn () => $this->period->update(['status' => PayrollPeriod::STATUS_PROCESSING]), reason: 'test setup');

        $this->period->transitionTo(PayrollPeriod::STATUS_PROCESSED);

        $this->period->refresh();
        $this->assertEquals(PayrollPeriod::STATUS_PROCESSED, $this->period->getState());
    }

    public function test_valid_transition_processed_to_closed_succeeds(): void
    {
        PayrollPeriod::withoutTenantCheck(fn () => $this->period->update(['status' => PayrollPeriod::STATUS_PROCESSED]), reason: 'test setup');

        $this->period->transitionTo(PayrollPeriod::STATUS_CLOSED);

        $this->period->refresh();
        $this->assertEquals(PayrollPeriod::STATUS_CLOSED, $this->period->getState());
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    public function test_invalid_transition_open_to_closed_throws(): void
    {
        $this->assertEquals(PayrollPeriod::STATUS_OPEN, $this->period->getState());

        $this->expectException(InvalidStateTransitionException::class);

        $this->period->transitionTo(PayrollPeriod::STATUS_CLOSED);
    }

    public function test_invalid_transition_closed_to_open_throws(): void
    {
        PayrollPeriod::withoutTenantCheck(fn () => $this->period->update(['status' => PayrollPeriod::STATUS_CLOSED]), reason: 'test setup');

        $this->expectException(InvalidStateTransitionException::class);

        $this->period->transitionTo(PayrollPeriod::STATUS_OPEN);
    }

    // -------------------------------------------------------------------------
    // canTransitionTo
    // -------------------------------------------------------------------------

    public function test_can_transition_to_returns_true_for_valid_transition(): void
    {
        $this->assertTrue($this->period->canTransitionTo(PayrollPeriod::STATUS_PROCESSING));
    }

    public function test_can_transition_to_returns_false_for_invalid_transition(): void
    {
        $this->assertFalse($this->period->canTransitionTo(PayrollPeriod::STATUS_CLOSED));
    }

    // -------------------------------------------------------------------------
    // Terminal state
    // -------------------------------------------------------------------------

    public function test_closed_is_terminal_state(): void
    {
        PayrollPeriod::withoutTenantCheck(fn () => $this->period->update(['status' => PayrollPeriod::STATUS_CLOSED]), reason: 'test setup');

        $this->assertTrue($this->period->isInTerminalState());
    }
}
