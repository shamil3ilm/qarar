<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Core;

use App\Models\Core\FailedJobMonitor;
use App\Services\Core\FailedJobMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FailedJobMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    private FailedJobMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FailedJobMonitorService();
    }

    // ==========================================
    // record()
    // ==========================================

    public function test_record_inserts_a_row_and_returns_its_id(): void
    {
        $id = $this->service->record(
            jobClass: 'App\Jobs\SendInvoiceJob',
            queue: 'invoices',
            payload: '{"id":1}',
            exception: 'RuntimeException: Connection refused',
            failedAt: '2026-04-01 10:00:00',
        );

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $this->assertDatabaseHas('failed_jobs_monitor', [
            'id'        => $id,
            'job_class' => 'App\Jobs\SendInvoiceJob',
            'queue'     => 'invoices',
        ]);
    }

    public function test_record_returns_incrementing_ids_for_separate_rows(): void
    {
        $id1 = $this->service->record(
            'App\Jobs\JobA', 'default', '{}', 'ExceptionA', '2026-04-01 10:00:00',
        );

        $id2 = $this->service->record(
            'App\Jobs\JobB', 'default', '{}', 'ExceptionB', '2026-04-01 10:01:00',
        );

        $this->assertNotEquals($id1, $id2);
        $this->assertCount(2, FailedJobMonitor::all());
    }

    // ==========================================
    // getRecentFailures()
    // ==========================================

    public function test_get_recent_failures_returns_only_rows_within_time_window(): void
    {
        $now = Carbon::now();

        // Within window
        FailedJobMonitor::create([
            'job_class'  => 'App\Jobs\RecentJob',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'RecentException',
            'failed_at'  => $now->copy()->subHours(2),
        ]);

        // Outside window (25 hours ago)
        FailedJobMonitor::create([
            'job_class'  => 'App\Jobs\OldJob',
            'queue'      => 'default',
            'payload'    => '{}',
            'exception'  => 'OldException',
            'failed_at'  => $now->copy()->subHours(25),
        ]);

        $results = $this->service->getRecentFailures(24);

        $this->assertCount(1, $results);
        $this->assertEquals('App\Jobs\RecentJob', $results->first()->job_class);
    }

    public function test_get_recent_failures_returns_results_ordered_newest_first(): void
    {
        $now = Carbon::now();

        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\OlderJob',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E1',
            'failed_at' => $now->copy()->subHours(5),
        ]);

        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\NewerJob',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E2',
            'failed_at' => $now->copy()->subHour(),
        ]);

        $results = $this->service->getRecentFailures(24);

        $this->assertEquals('App\Jobs\NewerJob', $results->first()->job_class);
        $this->assertEquals('App\Jobs\OlderJob', $results->last()->job_class);
    }

    public function test_get_recent_failures_returns_empty_collection_when_none_exist(): void
    {
        $results = $this->service->getRecentFailures(24);
        $this->assertCount(0, $results);
    }

    // ==========================================
    // cleanupOld()
    // ==========================================

    public function test_cleanup_old_deletes_rows_older_than_days_threshold(): void
    {
        $now = Carbon::now();

        // Old rows (31 days ago)
        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\StaleJobA',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E',
            'failed_at' => $now->copy()->subDays(31),
        ]);

        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\StaleJobB',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E',
            'failed_at' => $now->copy()->subDays(35),
        ]);

        // Recent row — must be preserved
        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\FreshJob',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E',
            'failed_at' => $now->copy()->subDays(1),
        ]);

        $deleted = $this->service->cleanupOld(30);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('failed_jobs_monitor', ['job_class' => 'App\Jobs\StaleJobA']);
        $this->assertDatabaseMissing('failed_jobs_monitor', ['job_class' => 'App\Jobs\StaleJobB']);
        $this->assertDatabaseHas('failed_jobs_monitor', ['job_class' => 'App\Jobs\FreshJob']);
    }

    public function test_cleanup_old_returns_zero_when_no_rows_qualify(): void
    {
        FailedJobMonitor::create([
            'job_class' => 'App\Jobs\FreshJob',
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'E',
            'failed_at' => Carbon::now()->subDays(5),
        ]);

        $deleted = $this->service->cleanupOld(30);

        $this->assertEquals(0, $deleted);
        $this->assertCount(1, FailedJobMonitor::all());
    }
}
