<?php

declare(strict_types=1);

namespace App\Listeners\Core;

use App\Services\Core\FailedJobMonitorService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronous listener that records failed-job metadata to the DB and to the
 * dedicated `failed_jobs_monitor` log channel.
 *
 * Must NOT implement ShouldQueue — running this asynchronously would mean a
 * queue worker failure could prevent the failure itself from being recorded.
 *
 * All exceptions are absorbed so that a bug in the monitor can never cascade
 * into further queue or application failures.
 */
class MonitorFailedJobListener
{
    public function __construct(
        private readonly FailedJobMonitorService $monitorService,
    ) {}

    public function handle(JobFailed $event): void
    {
        try {
            $jobClass  = get_class($event->job);
            $queue     = $event->job->getQueue() ?? 'default';
            $payload   = json_encode($event->job->payload(), JSON_UNESCAPED_UNICODE) ?: '';
            $exception = (string) $event->exception;
            $failedAt  = now()->toDateTimeString();

            $this->monitorService->record(
                $jobClass,
                $queue,
                $payload,
                $exception,
                $failedAt,
            );

            Log::channel('failed_jobs_monitor')->error('Job failed', [
                'job_class'  => $jobClass,
                'queue'      => $queue,
                'exception'  => $event->exception->getMessage(),
                'failed_at'  => $failedAt,
            ]);
        } catch (Throwable) {
            // Intentionally swallowed — monitor failures must not cascade.
        }
    }
}
