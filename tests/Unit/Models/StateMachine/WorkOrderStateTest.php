<?php

declare(strict_types=1);

namespace Tests\Unit\Models\StateMachine;

use App\Exceptions\ERP\InvalidStateTransitionException;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkOrderStateTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        // The factory resolves all required FK relations (bom_template, product, organization)
        $this->workOrder = WorkOrder::withoutTenantCheck(function () {
            return WorkOrder::factory()->create([
                'organization_id' => $this->organization->id,
                'status'          => WorkOrder::STATUS_DRAFT,
            ]);
        }, reason: 'unit test fixture');
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    public function test_valid_transition_draft_to_released_succeeds(): void
    {
        $this->assertEquals(WorkOrder::STATUS_DRAFT, $this->workOrder->getState());

        $this->workOrder->transitionTo(WorkOrder::STATUS_RELEASED);

        $this->workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_RELEASED, $this->workOrder->getState());
    }

    public function test_valid_transition_released_to_in_progress_succeeds(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_RELEASED]), reason: 'test setup');

        $this->workOrder->transitionTo(WorkOrder::STATUS_IN_PROGRESS);

        $this->workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->getState());
    }

    public function test_valid_transition_in_progress_to_completed_succeeds(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]), reason: 'test setup');

        $this->workOrder->transitionTo(WorkOrder::STATUS_COMPLETED);

        $this->workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $this->workOrder->getState());
    }

    public function test_valid_transition_completed_to_closed_succeeds(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_COMPLETED]), reason: 'test setup');

        $this->workOrder->transitionTo(WorkOrder::STATUS_CLOSED);

        $this->workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_CLOSED, $this->workOrder->getState());
    }

    public function test_valid_transition_draft_to_cancelled_succeeds(): void
    {
        $this->workOrder->transitionTo(WorkOrder::STATUS_CANCELLED);

        $this->workOrder->refresh();
        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $this->workOrder->getState());
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    public function test_invalid_transition_in_progress_to_released_throws(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]), reason: 'test setup');

        $this->expectException(InvalidStateTransitionException::class);

        $this->workOrder->transitionTo(WorkOrder::STATUS_RELEASED);
    }

    // -------------------------------------------------------------------------
    // Terminal state
    // -------------------------------------------------------------------------

    public function test_closed_is_terminal_state(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_CLOSED]), reason: 'test setup');

        $this->assertTrue($this->workOrder->isInTerminalState());
    }

    // -------------------------------------------------------------------------
    // Business method shortcuts
    // -------------------------------------------------------------------------

    public function test_start_method_transitions_to_in_progress_and_sets_actual_start_datetime(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update(['status' => WorkOrder::STATUS_RELEASED]), reason: 'test setup');

        // Capture a lower-bound one second in the past to account for sub-second SQLite rounding
        $before = now()->subSecond();
        $this->workOrder->start();
        $this->workOrder->refresh();

        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $this->workOrder->getState());
        $this->assertNotNull($this->workOrder->actual_start_datetime);
        $this->assertTrue(
            $this->workOrder->actual_start_datetime->greaterThanOrEqualTo($before),
            'actual_start_datetime should be set to approximately now()',
        );
    }

    public function test_complete_method_transitions_to_completed_and_sets_actual_end_datetime(): void
    {
        WorkOrder::withoutTenantCheck(fn () => $this->workOrder->update([
            'status'                => WorkOrder::STATUS_IN_PROGRESS,
            'actual_start_datetime' => now()->subHour(),
        ]), reason: 'test setup');

        // Capture a lower-bound one second in the past to account for sub-second SQLite rounding
        $before = now()->subSecond();
        $this->workOrder->complete();
        $this->workOrder->refresh();

        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $this->workOrder->getState());
        $this->assertNotNull($this->workOrder->actual_end_datetime);
        $this->assertTrue(
            $this->workOrder->actual_end_datetime->greaterThanOrEqualTo($before),
            'actual_end_datetime should be set to approximately now()',
        );
    }

    public function test_cancel_method_transitions_to_cancelled_and_sets_reason(): void
    {
        $reason = 'Material shortage — postponed to next quarter';

        $this->workOrder->cancel($reason);
        $this->workOrder->refresh();

        $this->assertEquals(WorkOrder::STATUS_CANCELLED, $this->workOrder->getState());
        $this->assertEquals($reason, $this->workOrder->cancellation_reason);
    }
}
