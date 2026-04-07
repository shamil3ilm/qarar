<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\MessageCampaign;
use App\Models\Messaging\OutboundMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        private MessageService $messageService
    ) {}

    /**
     * Create a new campaign (messaging automation).
     */
    public function create(array $data, int $userId): MessageCampaign
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['is_active'] = $data['is_active'] ?? false;
            $data['execution_count'] = 0;
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['timing'] = $data['timing'] ?? MessageCampaign::TIMING_IMMEDIATE;

            return MessageCampaign::create($data);
        });
    }

    /**
     * Add recipients to a campaign by queuing outbound messages.
     */
    public function addRecipients(MessageCampaign $campaign, array $recipients, int $userId): array
    {
        $results = [];

        return DB::transaction(function () use ($campaign, $recipients, $userId, &$results) {
            foreach ($recipients as $recipient) {
                try {
                    $message = $this->messageService->queueMessage([
                        'organization_id' => $campaign->organization_id,
                        'automation_id' => $campaign->id,
                        'template_id' => $campaign->template_id,
                        'channel_id' => $campaign->channel_id,
                        'channel_type' => $campaign->channel_type,
                        'recipient' => $recipient['address'] ?? $recipient['email'] ?? $recipient['phone'] ?? '',
                        'recipient_name' => $recipient['name'] ?? null,
                        'contact_id' => $recipient['contact_id'] ?? null,
                        'category' => $campaign->template?->category ?? 'promotional',
                        'template_data' => $recipient['data'] ?? [],
                    ], $userId);

                    $results[] = [
                        'recipient' => $message->recipient,
                        'status' => 'queued',
                        'message_id' => $message->uuid,
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'recipient' => $recipient['address'] ?? $recipient['email'] ?? 'unknown',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return $results;
        });
    }

    /**
     * Launch a campaign - activate it and start processing.
     */
    public function launch(MessageCampaign $campaign): MessageCampaign
    {
        return DB::transaction(function () use ($campaign) {
            if (!$campaign->isActive()) {
                $campaign->update(['is_active' => true]);
            }

            // If immediate, process queued messages now
            if ($campaign->isImmediate()) {
                $this->processQueuedMessages($campaign);
            }

            return $campaign->fresh();
        });
    }

    /**
     * Pause a campaign - deactivate it temporarily.
     */
    public function pause(MessageCampaign $campaign): MessageCampaign
    {
        if (!$campaign->isActive()) {
            throw new \InvalidArgumentException('Campaign is not active.');
        }

        $campaign->update(['is_active' => false]);

        return $campaign->fresh();
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(MessageCampaign $campaign): MessageCampaign
    {
        if ($campaign->isActive()) {
            throw new \InvalidArgumentException('Campaign is already active.');
        }

        return DB::transaction(function () use ($campaign) {
            $campaign->update(['is_active' => true]);

            if ($campaign->isImmediate()) {
                $this->processQueuedMessages($campaign);
            }

            return $campaign->fresh();
        });
    }

    /**
     * Cancel a campaign - deactivate and cancel all pending messages.
     */
    public function cancel(MessageCampaign $campaign): MessageCampaign
    {
        return DB::transaction(function () use ($campaign) {
            $campaign->update(['is_active' => false]);

            // Cancel all queued messages
            OutboundMessage::where('automation_id', $campaign->id)
                ->where('status', OutboundMessage::STATUS_QUEUED)
                ->update([
                    'status' => OutboundMessage::STATUS_FAILED,
                    'failure_reason' => 'Campaign cancelled',
                ]);

            return $campaign->fresh();
        });
    }

    /**
     * Get campaign statistics.
     */
    public function getStats(MessageCampaign $campaign): array
    {
        $messages = OutboundMessage::where('automation_id', $campaign->id);

        $total = (clone $messages)->count();
        $queued = (clone $messages)->queued()->count();
        $sent = (clone $messages)->sent()->count();
        $failed = (clone $messages)->failed()->count();
        $opened = (clone $messages)->whereNotNull('opened_at')->count();
        $clicked = (clone $messages)->whereNotNull('clicked_at')->count();
        $bounced = (clone $messages)->where('status', OutboundMessage::STATUS_BOUNCED)->count();

        $deliveryRate = $total > 0 ? round(($sent / $total) * 100, 2) : 0;
        $openRate = $sent > 0 ? round(($opened / $sent) * 100, 2) : 0;
        $clickRate = $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0;
        $bounceRate = $total > 0 ? round(($bounced / $total) * 100, 2) : 0;

        $totalCost = (clone $messages)->sum('cost');

        return [
            'campaign_id' => $campaign->uuid,
            'campaign_name' => $campaign->name,
            'is_active' => $campaign->is_active,
            'total_recipients' => $total,
            'queued' => $queued,
            'sent' => $sent,
            'failed' => $failed,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'delivery_rate' => $deliveryRate,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'bounce_rate' => $bounceRate,
            'total_cost' => (float) $totalCost,
            'execution_count' => $campaign->execution_count,
            'last_executed_at' => $campaign->last_executed_at?->toISOString(),
        ];
    }

    /**
     * Process queued messages for a campaign.
     */
    protected function processQueuedMessages(MessageCampaign $campaign): void
    {
        $messages = OutboundMessage::where('automation_id', $campaign->id)
            ->where('status', OutboundMessage::STATUS_QUEUED)
            ->get();

        foreach ($messages as $message) {
            try {
                $this->messageService->sendMessage($message);
            } catch (\Throwable $e) {
                Log::error('Campaign message sending failed', [
                    'campaign_id' => $campaign->id,
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $campaign->incrementExecutionCount();
    }
}
