<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignSend;
use App\Models\User;
use App\Services\Campaign\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignForUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $campaignId,
        private readonly int $userId,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(CampaignService $campaignService): void
    {
        // withoutGlobalScopes() is required in queue context — no authenticated user
        // means tenant-scoped global scopes would filter out all records.
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);
        $user     = User::withoutGlobalScopes()->find($this->userId);

        if ($campaign === null || $user === null) {
            Log::warning('ProcessCampaignForUserJob — campaign or user not found', [
                'campaign_id' => $this->campaignId,
                'user_id'     => $this->userId,
            ]);
            return;
        }

        $campaignService->processForUser($campaign, $user);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCampaignForUserJob failed', [
            'campaign_id' => $this->campaignId,
            'user_id'     => $this->userId,
            'error'       => $exception->getMessage(),
        ]);

        // Mark any pending send record as failed.
        // withoutGlobalScopes() is required — failed() runs without an authenticated user.
        CampaignSend::withoutGlobalScopes()
            ->where('campaign_id', $this->campaignId)
            ->where('user_id', $this->userId)
            ->where('status', CampaignSend::STATUS_PENDING)
            ->update(['status' => CampaignSend::STATUS_FAILED]);
    }
}
