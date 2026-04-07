<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReevaluateSegmentMembershipsJob;
use App\Services\Campaign\SegmentService;
use Illuminate\Console\Command;

class ReevaluateSegmentsCommand extends Command
{
    protected $signature = 'segments:reevaluate {--org= : Organization ID to limit scope}';

    protected $description = 'Re-evaluate all dynamic segment memberships';

    public function handle(SegmentService $segmentService): int
    {
        $orgId = $this->option('org');

        if ($orgId !== null) {
            $organizationId = (int) $orgId;
            $this->info("Re-evaluating segments for organization {$organizationId}...");
            $segmentService->reevaluateAllForOrganization($organizationId);
            $this->info('Done.');
            return self::SUCCESS;
        }

        $this->info('Dispatching ReevaluateSegmentMembershipsJob for all organizations...');
        ReevaluateSegmentMembershipsJob::dispatch();
        $this->info('Job dispatched.');

        return self::SUCCESS;
    }
}
