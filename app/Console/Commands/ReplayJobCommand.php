<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Core\TrackedJob;
use App\Services\Core\JobTrackingService;
use Illuminate\Console\Command;

class ReplayJobCommand extends Command
{
    protected $signature = 'erp:replay-job
        {--id= : Replay a specific TrackedJob by ID}
        {--class= : Replay all failed jobs of a specific class}
        {--org= : Limit replay to specific organization_id}
        {--dry-run : List what would be replayed without doing it}';

    protected $description = 'Replay failed tracked jobs';

    public function __construct(private readonly JobTrackingService $tracker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($id = $this->option('id')) {
            $job = TrackedJob::findOrFail((int) $id);
            if ($dryRun) {
                $this->info("[DRY RUN] Would replay: [{$job->id}] {$job->job_class}");
                return self::SUCCESS;
            }
            $this->tracker->replay($job);
            $this->info("Replayed job #{$job->id} ({$job->job_class})");
            return self::SUCCESS;
        }

        $jobs = $this->tracker->findReplayable(
            $this->option('class'),
            $this->option('org') ? (int) $this->option('org') : null
        );

        if ($jobs->isEmpty()) {
            $this->info('No failed jobs found to replay.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Job Class', 'Org', 'Attempts', 'Failed At'],
            $jobs->map(fn($j) => [$j->id, class_basename($j->job_class), $j->organization_id, $j->attempts, $j->updated_at?->toDateTimeString()])
        );

        if ($dryRun) {
            $this->info("[DRY RUN] Would replay {$jobs->count()} jobs.");
            return self::SUCCESS;
        }

        if (! $this->confirm("Replay {$jobs->count()} failed job(s)?")) {
            return self::SUCCESS;
        }

        $replayed = 0;
        foreach ($jobs as $job) {
            try {
                $this->tracker->replay($job);
                $replayed++;
            } catch (\Throwable $e) {
                $this->warn("Failed to replay #{$job->id}: {$e->getMessage()}");
            }
        }

        $this->info("Replayed {$replayed}/{$jobs->count()} jobs.");
        return self::SUCCESS;
    }
}
