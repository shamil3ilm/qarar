<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Jobs\ProcessCampaignForUserJob;
use App\Jobs\TriggerEventCampaignsJob;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignSend;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly SegmentService $segmentService,
    ) {
    }

    /**
     * Dispatch an async job to trigger event-based campaigns without adding request latency.
     */
    public static function triggerEventAsync(string $event, int $userId, int $organizationId): void
    {
        TriggerEventCampaignsJob::dispatch($event, $userId, $organizationId);
    }

    /**
     * Find and process all active campaigns matching the given event for the user/org.
     */
    public function triggerEvent(string $event, int $userId, int $organizationId, array $context = []): void
    {
        $user = User::where('id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($user === null) {
            Log::warning('CampaignService::triggerEvent — user not found', [
                'user_id' => $userId,
                'event'   => $event,
            ]);
            return;
        }

        $campaigns = Campaign::where('trigger_event', $event)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                if (!$campaign->isRunnable()) {
                    continue;
                }

                // Check segment membership if a segment is set
                if ($campaign->target_segment_id !== null) {
                    $segment = $campaign->segment;
                    if ($segment === null || !$this->segmentService->userMatchesSegment($user, $segment)) {
                        continue;
                    }
                }

                // Evaluate extra campaign-level conditions
                if (!empty($campaign->conditions) && !$this->evaluator->evaluate($campaign->conditions, $user)) {
                    continue;
                }

                if ($this->hasAlreadyReceived($campaign, $userId)) {
                    continue;
                }

                ProcessCampaignForUserJob::dispatch($campaign->id, $userId);
            } catch (\Throwable $e) {
                Log::error('CampaignService::triggerEvent error processing campaign', [
                    'campaign_id' => $campaign->id,
                    'user_id'     => $userId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute all actions for a campaign targeted at a specific user.
     */
    public function processForUser(Campaign $campaign, User $user): void
    {
        $send = CampaignSend::create([
            'campaign_id'     => $campaign->id,
            'user_id'         => $user->id,
            'organization_id' => $campaign->organization_id,
            'status'          => CampaignSend::STATUS_PENDING,
        ]);

        $hasFailure = false;

        foreach ($campaign->actions as $action) {
            try {
                $this->executeAction($action, $user);
            } catch (\Throwable $e) {
                $hasFailure = true;
                Log::error('Campaign action failed', [
                    'campaign_id' => $campaign->id,
                    'user_id'     => $user->id,
                    'action_type' => $action['type'] ?? 'unknown',
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $send->update([
            'status'  => $hasFailure ? CampaignSend::STATUS_FAILED : CampaignSend::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    private function executeAction(array $action, User $user): void
    {
        $type = $action['type'] ?? '';

        match ($type) {
            'notification' => $this->executeNotificationAction($action, $user),
            'sms'          => $this->executeSmsAction($action, $user),
            'database'     => $this->executeDatabaseAction($action, $user),
            default        => Log::warning('Unknown campaign action type', ['type' => $type]),
        };
    }

    private function executeNotificationAction(array $action, User $user): void
    {
        $class = $action['class'] ?? '';

        if ($class === '' || !class_exists($class)) {
            Log::warning('Campaign notification class not found', ['class' => $class]);
            return;
        }

        $args = $action['args'] ?? [];
        $user->notify(new $class(...$args));
    }

    private function executeSmsAction(array $action, User $user): void
    {
        if ($user->phone === null || $user->phone === '') {
            return;
        }

        $message = $action['message'] ?? '';

        if ($message === '') {
            return;
        }

        /** @var \App\Services\Messaging\SmsService $smsService */
        $smsService = app(\App\Services\Messaging\SmsService::class);
        $smsService->send($user->phone, $message);
    }

    private function executeDatabaseAction(array $action, User $user): void
    {
        $user->notifications()->create([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'type'            => $action['notification_type'] ?? 'campaign',
            'notifiable_type' => User::class,
            'notifiable_id'   => $user->id,
            'data'            => json_encode($action['data'] ?? []),
        ]);
    }

    private function hasAlreadyReceived(Campaign $campaign, int $userId): bool
    {
        $count = CampaignSend::where('campaign_id', $campaign->id)
            ->where('user_id', $userId)
            ->whereIn('status', [CampaignSend::STATUS_SENT, CampaignSend::STATUS_PENDING])
            ->count();

        return $count >= $campaign->max_sends_per_user;
    }
}
