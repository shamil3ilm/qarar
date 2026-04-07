<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\JobMonitor;
use App\Models\Core\JobMonitorLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use RuntimeException;

class JobMonitorService
{
    public function register(string $jobClass, string $jobName, array $options = []): JobMonitor
    {
        return JobMonitor::create([
            'organization_id'      => $options['organization_id'] ?? null,
            'job_class'            => $jobClass,
            'job_name'             => $jobName,
            'queue_name'           => $options['queue_name'] ?? 'default',
            'status'               => JobMonitor::STATUS_QUEUED,
            'payload'              => $options['payload'] ?? null,
            'max_attempts'         => $options['max_attempts'] ?? 3,
            'triggered_by'         => $options['triggered_by'] ?? JobMonitor::TRIGGERED_MANUAL,
            'triggered_by_user_id' => $options['triggered_by_user_id'] ?? null,
            'tags'                 => $options['tags'] ?? null,
            'queued_at'            => Carbon::now(),
        ]);
    }

    public function markRunning(int $monitorId): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markRunning();
    }

    public function markCompleted(int $monitorId, string $output = ''): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markCompleted($output);
    }

    public function markFailed(int $monitorId, string $error): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->markFailed($error);
    }

    public function updateProgress(int $monitorId, int $percentage, string $message = ''): void
    {
        $monitor = $this->findOrFail($monitorId);
        $monitor->updateProgress($percentage, $message);
    }

    public function log(int $monitorId, string $level, string $message, array $context = []): void
    {
        JobMonitorLog::create([
            'job_monitor_id' => $monitorId,
            'level'          => $level,
            'message'        => $message,
            'context'        => !empty($context) ? $context : null,
            'created_at'     => Carbon::now(),
        ]);
    }

    public function getStats(?int $organizationId = null): array
    {
        $query = JobMonitor::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $total    = (clone $query)->count();
        $queued   = (clone $query)->where('status', JobMonitor::STATUS_QUEUED)->count();
        $running  = (clone $query)->where('status', JobMonitor::STATUS_RUNNING)->count();
        $completed = (clone $query)->where('status', JobMonitor::STATUS_COMPLETED)->count();
        $failed   = (clone $query)->where('status', JobMonitor::STATUS_FAILED)->count();
        $retrying = (clone $query)->where('status', JobMonitor::STATUS_RETRYING)->count();

        $avgDuration = (clone $query)
            ->where('status', JobMonitor::STATUS_COMPLETED)
            ->whereNotNull('run_duration_seconds')
            ->avg('run_duration_seconds');

        $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

        return [
            'total'         => $total,
            'queued'        => $queued,
            'running'       => $running,
            'completed'     => $completed,
            'failed'        => $failed,
            'retrying'      => $retrying,
            'avg_duration'  => $avgDuration ? round((float) $avgDuration, 2) : null,
            'failure_rate'  => $failureRate,
        ];
    }

    public function getFailedJobs(?int $organizationId = null): Collection
    {
        $query = JobMonitor::failed()->with('triggeredByUser')->orderByDesc('failed_at');

        if ($organizationId !== null) {
            $query->forOrganization($organizationId);
        }

        return $query->get();
    }

    public function getRunningJobs(): Collection
    {
        return JobMonitor::running()
            ->with('triggeredByUser')
            ->orderBy('started_at')
            ->get();
    }

    public function retryFailed(int $monitorId): void
    {
        $monitor = $this->findOrFail($monitorId);

        if ($monitor->status !== JobMonitor::STATUS_FAILED) {
            throw new RuntimeException('Only failed jobs can be retried.');
        }

        if ($monitor->attempts >= $monitor->max_attempts) {
            throw new RuntimeException("Job has reached max attempts ({$monitor->max_attempts}).");
        }

        $monitor->update([
            'status'        => JobMonitor::STATUS_RETRYING,
            'error_message' => null,
            'failed_at'     => null,
            'next_retry_at' => Carbon::now()->addSeconds(30),
        ]);

        $this->log($monitorId, JobMonitorLog::LEVEL_INFO, 'Job queued for retry', [
            'attempt' => $monitor->attempts + 1,
        ]);
    }

    public function cleanOldRecords(int $daysToKeep = 30): int
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);

        $ids = JobMonitor::whereIn('status', [JobMonitor::STATUS_COMPLETED, JobMonitor::STATUS_FAILED])
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        JobMonitorLog::whereIn('job_monitor_id', $ids)->delete();
        JobMonitor::whereIn('id', $ids)->delete();

        return $ids->count();
    }

    private function findOrFail(int $monitorId): JobMonitor
    {
        return JobMonitor::findOrFail($monitorId);
    }
}
