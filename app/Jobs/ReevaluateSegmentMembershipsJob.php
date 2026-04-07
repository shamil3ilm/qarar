<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Core\Organization;
use App\Services\Campaign\SegmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReevaluateSegmentMembershipsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Prevent duplicate jobs from being queued within 1 hour. */
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'reevaluate_segments';
    }

    public function __construct()
    {
        $this->onQueue('segments');
    }

    public function handle(SegmentService $segmentService): void
    {
        Organization::where('is_active', true)
            ->whereNull('deleted_at')
            ->chunk(50, function ($organizations) use ($segmentService) {
                foreach ($organizations as $organization) {
                    try {
                        $segmentService->reevaluateAllForOrganization($organization->id);
                    } catch (\Throwable $e) {
                        Log::error('ReevaluateSegmentMembershipsJob — org failed', [
                            'organization_id' => $organization->id,
                            'error'           => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReevaluateSegmentMembershipsJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
