<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\FailedJobMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Persists and queries failed-job records in the `failed_jobs_monitor` table.
 *
 * This service is intentionally free of notification dispatch so it can be
 * called synchronously from a listener without adding latency.
 */
class FailedJobMonitorService
{
    /**
     * Insert a new failed-job record and return its auto-increment ID.
     */
    public function record(
        string $jobClass,
        string $queue,
        string $payload,
        string $exception,
        string $failedAt,
    ): int {
        $model = FailedJobMonitor::create([
            'job_class'  => $jobClass,
            'queue'      => $queue,
            'payload'    => $payload,
            'exception'  => $exception,
            'failed_at'  => $failedAt,
        ]);

        return $model->id;
    }

    /**
     * Return all failures recorded within the last $hours hours, newest first.
     *
     * @return Collection<int, FailedJobMonitor>
     */
    public function getRecentFailures(int $hours = 24): Collection
    {
        return FailedJobMonitor::query()
            ->where('failed_at', '>=', Carbon::now()->subHours($hours))
            ->orderByDesc('failed_at')
            ->get();
    }

    /**
     * Delete rows older than $days days and return the number of rows removed.
     */
    public function cleanupOld(int $days = 30): int
    {
        return FailedJobMonitor::query()
            ->where('failed_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }
}
