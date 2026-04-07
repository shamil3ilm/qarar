<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Core\Organization;
use App\Services\Analytics\UserClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClusterUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(UserClusteringService $clusteringService): void
    {
        Organization::query()
            ->where('is_active', true)
            ->chunk(50, function ($organizations) use ($clusteringService) {
                foreach ($organizations as $organization) {
                    try {
                        $clusteringService->clusterAllUsers($organization->id);
                    } catch (\Throwable $e) {
                        Log::error('ClusterUsersJob: failed for organization', [
                            'organization_id' => $organization->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ClusterUsersJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
