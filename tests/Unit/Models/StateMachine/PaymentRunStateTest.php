<?php

declare(strict_types=1);

namespace Tests\Unit\Models\StateMachine;

use App\Exceptions\ERP\InvalidStateTransitionException;
use App\Models\Accounting\PaymentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentRunStateTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private PaymentRun $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->run = PaymentRun::withoutTenantCheck(function () {
            return PaymentRun::create([
                'organization_id'   => $this->organization->id,
                'run_reference'     => 'RUN-' . Str::random(6),
                'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
                'payment_date'      => now()->toDateString(),
                'currency_code'     => 'SAR',
                'status'            => PaymentRun::STATUS_DRAFT,
                'created_by'        => $this->user->id,
            ]);
        }, reason: 'unit test fixture');
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    public function test_valid_transition_draft_to_proposed_succeeds(): void
    {
        $this->assertEquals(PaymentRun::STATUS_DRAFT, $this->run->getState());

        $this->run->transitionTo(PaymentRun::STATUS_PROPOSED);

        $this->run->refresh();
        $this->assertEquals(PaymentRun::STATUS_PROPOSED, $this->run->getState());
    }

    public function test_valid_transition_proposed_to_approved_succeeds(): void
    {
        PaymentRun::withoutTenantCheck(fn () => $this->run->update(['status' => PaymentRun::STATUS_PROPOSED]), reason: 'test setup');

        $this->run->transitionTo(PaymentRun::STATUS_APPROVED);

        $this->run->refresh();
        $this->assertEquals(PaymentRun::STATUS_APPROVED, $this->run->getState());
    }

    public function test_valid_transition_approved_to_posted_succeeds(): void
    {
        PaymentRun::withoutTenantCheck(fn () => $this->run->update(['status' => PaymentRun::STATUS_APPROVED]), reason: 'test setup');

        $this->run->transitionTo(PaymentRun::STATUS_POSTED);

        $this->run->refresh();
        $this->assertEquals(PaymentRun::STATUS_POSTED, $this->run->getState());
    }

    public function test_valid_transition_draft_to_cancelled_succeeds(): void
    {
        $this->run->transitionTo(PaymentRun::STATUS_CANCELLED);

        $this->run->refresh();
        $this->assertEquals(PaymentRun::STATUS_CANCELLED, $this->run->getState());
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    public function test_invalid_transition_posted_to_draft_throws(): void
    {
        PaymentRun::withoutTenantCheck(fn () => $this->run->update(['status' => PaymentRun::STATUS_POSTED]), reason: 'test setup');

        $this->expectException(InvalidStateTransitionException::class);

        $this->run->transitionTo(PaymentRun::STATUS_DRAFT);
    }

    // -------------------------------------------------------------------------
    // Terminal states
    // -------------------------------------------------------------------------

    public function test_posted_is_terminal_state(): void
    {
        PaymentRun::withoutTenantCheck(fn () => $this->run->update(['status' => PaymentRun::STATUS_POSTED]), reason: 'test setup');

        $this->assertTrue($this->run->isInTerminalState());
    }

    public function test_cancelled_is_terminal_state(): void
    {
        PaymentRun::withoutTenantCheck(fn () => $this->run->update(['status' => PaymentRun::STATUS_CANCELLED]), reason: 'test setup');

        $this->assertTrue($this->run->isInTerminalState());
    }
}
