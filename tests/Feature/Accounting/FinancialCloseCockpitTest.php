<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FinancialCloseTask;
use App\Models\Accounting\FinancialClosePeriod;
use App\Models\Accounting\FinancialCloseTemplate;
use App\Models\Accounting\FinancialCloseTemplateTask;
use App\Services\Accounting\FinancialCloseCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FinancialCloseCockpitTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private FinancialCloseCockpitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(FinancialCloseCockpitService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeTemplate(int $taskCount = 3): FinancialCloseTemplate
    {
        $template = FinancialCloseTemplate::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        for ($i = 1; $i <= $taskCount; $i++) {
            FinancialCloseTemplateTask::factory()->create([
                'financial_close_template_id' => $template->id,
                'task_name'                   => "Task {$i}",
                'sort_order'                  => $i,
            ]);
        }

        return $template->load('tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getPeriodProgress() — existing, regression guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_period_progress_returns_correct_counts(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FinancialClosePeriod::STATUS_OPEN,
        ]);

        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);
        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $progress = $this->service->getPeriodProgress($period);

        $this->assertEquals(2, $progress['total']);
        $this->assertEquals(1, $progress['pending']);
        $this->assertEquals(1, $progress['completed']);
        $this->assertEquals(50.0, $progress['percent_complete']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // schedulePeriod() — FI-10 automated scheduling
    // ─────────────────────────────────────────────────────────────────────────

    public function test_schedule_period_creates_period_with_tasks(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $this->assertEquals(3, $period->tasks->count());
    }

    public function test_schedule_period_assigns_due_dates_to_tasks(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $dueDates = $period->tasks->pluck('due_date')->filter()->values();

        $this->assertEquals(3, $dueDates->count());
    }

    public function test_schedule_period_last_task_has_period_due_date(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $tasks   = $period->tasks->sortBy('sort_order')->values();
        $lastDue = $tasks->last()->due_date;

        $this->assertEquals('2025-03-31', $lastDue->format('Y-m-d'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // blockDependents() — FI-10 dependency chain enforcement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_block_dependents_marks_downstream_tasks_blocked(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $critical = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'task_name'                 => 'Critical',
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        $dependent = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'task_name'                 => 'Dependent',
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        // Wire dependency: dependent depends on critical
        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            'financial_close_task_id' => $dependent->id,
            'depends_on_task_id'      => $critical->id,
        ]);

        $this->service->blockDependents($critical);

        $this->assertEquals(
            FinancialCloseTask::STATUS_BLOCKED,
            $dependent->fresh()->status
        );
    }

    public function test_block_dependents_does_not_affect_in_progress_tasks(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $critical = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        $inProgress = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            'financial_close_task_id' => $inProgress->id,
            'depends_on_task_id'      => $critical->id,
        ]);

        $this->service->blockDependents($critical);

        // In-progress tasks should not be changed
        $this->assertEquals(
            FinancialCloseTask::STATUS_IN_PROGRESS,
            $inProgress->fresh()->status
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // propagateBlocked() — unblocking when dependencies resolve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_propagate_blocked_restores_pending_when_dependencies_done(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $dep1 = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $dep2 = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $blocked = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_BLOCKED,
        ]);

        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            ['financial_close_task_id' => $blocked->id, 'depends_on_task_id' => $dep1->id],
            ['financial_close_task_id' => $blocked->id, 'depends_on_task_id' => $dep2->id],
        ]);

        $this->service->propagateBlocked($dep2);

        $this->assertEquals(
            FinancialCloseTask::STATUS_PENDING,
            $blocked->fresh()->status
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existing task lifecycle — regression guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_start_task_transitions_pending_to_in_progress(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $task = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        $this->service->startTask($task, $this->user->id);

        $this->assertEquals(FinancialCloseTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_close_period_requires_all_tasks_done(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->closePeriod($period, $this->user->id);
    }
}
