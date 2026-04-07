<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Campaign\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TriggerEventCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly string $event,
        private readonly int $userId,
        private readonly int $organizationId,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(CampaignService $campaignService): void
    {
        $campaignService->triggerEvent($this->event, $this->userId, $this->organizationId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TriggerEventCampaignsJob failed', [
            'event'           => $this->event,
            'user_id'         => $this->userId,
            'organization_id' => $this->organizationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
