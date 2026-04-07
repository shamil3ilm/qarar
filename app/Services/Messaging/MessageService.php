<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\MessageTemplate;
use App\Models\Messaging\MessagingConfiguration;
use App\Models\Messaging\NotificationPreference;
use App\Models\Messaging\OutboundMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageService
{
    /**
     * Create a new message template.
     */
    public function createTemplate(array $data): MessageTemplate
    {
        return DB::transaction(function () use ($data) {
            $data['is_active'] = $data['is_active'] ?? true;
            $data['is_system'] = $data['is_system'] ?? false;
            $data['language'] = $data['language'] ?? 'en';

            return MessageTemplate::create($data);
        });
    }

    /**
     * Render a template with given data.
     */
    public function renderTemplate(MessageTemplate $template, array $data): array
    {
        return $template->render($data);
    }

    /**
     * Queue a message for sending.
     */
    public function queueMessage(array $messageData, int $userId): OutboundMessage
    {
        return DB::transaction(function () use ($messageData, $userId) {
            $messageData['status'] = OutboundMessage::STATUS_QUEUED;
            $messageData['triggered_by'] = $messageData['triggered_by'] ?? $userId;

            // Resolve channel configuration if not set
            if (empty($messageData['channel_id']) && !empty($messageData['channel_type'])) {
                $channel = MessagingConfiguration::getDefault($messageData['channel_type']);
                if ($channel) {
                    $messageData['channel_id'] = $channel->id;
                    $messageData['sender'] = $messageData['sender'] ?? $channel->sender_address;
                }
            }

            // Check contact preferences if contact_id is provided
            if (!empty($messageData['contact_id'])) {
                $preference = NotificationPreference::where('contact_id', $messageData['contact_id'])->first();
                if ($preference) {
                    $channelType = $messageData['channel_type'] ?? 'email';
                    $category = $messageData['category'] ?? 'transactional';

                    if (!$preference->canReceiveMessage($channelType, $category)) {
                        Log::info('Message blocked by contact preferences', [
                            'contact_id' => $messageData['contact_id'],
                            'channel_type' => $channelType,
                            'category' => $category,
                        ]);

                        // Still create the record but mark as failed
                        $messageData['status'] = OutboundMessage::STATUS_FAILED;
                        $messageData['failure_reason'] = 'Contact has opted out of this message type';
                    }

                    // Check quiet hours
                    if ($preference->isInQuietHours() && $category !== 'transactional') {
                        $messageData['next_retry_at'] = now()->addHours(1);
                    }
                }
            }

            // Render template content if template is provided
            if (!empty($messageData['template_id']) && empty($messageData['body'])) {
                $template = MessageTemplate::find($messageData['template_id']);
                if ($template) {
                    $rendered = $template->render($messageData['template_data'] ?? []);
                    $messageData['subject'] = $rendered['subject'] ?? $messageData['subject'] ?? null;
                    $messageData['body'] = $rendered['body'];
                    $messageData['html_body'] = $rendered['html_body'] ?? null;
                }
                unset($messageData['template_data']);
            }

            return OutboundMessage::create($messageData);
        });
    }

    /**
     * Send a single message immediately.
     */
    public function sendMessage(OutboundMessage $message): bool
    {
        try {
            $message->markAsSending();

            $channel = $message->channel;

            if (!$channel || !$channel->isActive()) {
                $message->markAsFailed('Channel not available or inactive');
                return false;
            }

            // Dispatch to the appropriate channel handler
            $result = $this->dispatchToChannel($message, $channel);

            if ($result['success']) {
                $message->markAsSent($result['provider_message_id'] ?? null);

                if (!empty($result['provider_response'])) {
                    $message->update(['provider_response' => $result['provider_response']]);
                }

                return true;
            }

            $message->markAsFailed(
                $result['error'] ?? 'Unknown error',
                $result['provider_response'] ?? null
            );

            return false;
        } catch (\Throwable $e) {
            Log::error('Message sending failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Process the message queue - send all queued messages.
     */
    public function processQueue(int $batchSize = 100): array
    {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        $messages = OutboundMessage::queued()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        foreach ($messages as $message) {
            $results['processed']++;

            if ($this->sendMessage($message)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        // Also retry failed messages
        $retryable = OutboundMessage::retryable()
            ->limit($batchSize)
            ->get();

        foreach ($retryable as $message) {
            $results['processed']++;

            if ($this->sendMessage($message)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get delivery statistics.
     */
    public function getDeliveryStats(?int $days = 30, ?string $channelType = null): array
    {
        $query = OutboundMessage::recent($days);

        if ($channelType) {
            $query->forChannel($channelType);
        }

        $total = (clone $query)->count();
        $sent = (clone $query)->sent()->count();
        $failed = (clone $query)->failed()->count();
        $queued = (clone $query)->queued()->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();

        $deliveryRate = $total > 0 ? round(($sent / $total) * 100, 2) : 0;
        $openRate = $sent > 0 ? round(($opened / $sent) * 100, 2) : 0;
        $clickRate = $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0;

        $byChannel = OutboundMessage::recent($days)
            ->selectRaw('channel_type, status, count(*) as count')
            ->groupBy('channel_type', 'status')
            ->get()
            ->groupBy('channel_type')
            ->map(fn($group) => $group->pluck('count', 'status'));

        $totalCost = (clone $query)->sum('cost');

        return [
            'period_days' => $days,
            'total' => $total,
            'queued' => $queued,
            'sent' => $sent,
            'failed' => $failed,
            'opened' => $opened,
            'clicked' => $clicked,
            'delivery_rate' => $deliveryRate,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'total_cost' => (float) $totalCost,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * Dispatch a message to the appropriate channel handler.
     */
    protected function dispatchToChannel(OutboundMessage $message, MessagingConfiguration $channel): array
    {
        return match ($channel->channel_type) {
            MessagingConfiguration::CHANNEL_EMAIL => $this->sendViaEmail($message, $channel),
            MessagingConfiguration::CHANNEL_SMS => $this->sendViaSms($message, $channel),
            MessagingConfiguration::CHANNEL_WHATSAPP => $this->sendViaWhatsApp($message, $channel),
            MessagingConfiguration::CHANNEL_PUSH => $this->sendViaPush($message, $channel),
            default => ['success' => false, 'error' => "Unsupported channel type: {$channel->channel_type}"],
        };
    }

    /**
     * Send message via email.
     */
    protected function sendViaEmail(OutboundMessage $message, MessagingConfiguration $channel): array
    {
        // Placeholder for actual email sending implementation
        // In production, this would use Laravel Mail, SendGrid API, etc.
        Log::info('Sending email message', [
            'message_id' => $message->id,
            'provider' => $channel->provider,
            'recipient' => $message->recipient,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'email_' . uniqid(),
        ];
    }

    /**
     * Send message via SMS.
     */
    protected function sendViaSms(OutboundMessage $message, MessagingConfiguration $channel): array
    {
        Log::info('Sending SMS message', [
            'message_id' => $message->id,
            'provider' => $channel->provider,
            'recipient' => $message->recipient,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'sms_' . uniqid(),
        ];
    }

    /**
     * Send message via WhatsApp.
     */
    protected function sendViaWhatsApp(OutboundMessage $message, MessagingConfiguration $channel): array
    {
        Log::info('Sending WhatsApp message', [
            'message_id' => $message->id,
            'provider' => $channel->provider,
            'recipient' => $message->recipient,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'wa_' . uniqid(),
        ];
    }

    /**
     * Send message via push notification.
     */
    protected function sendViaPush(OutboundMessage $message, MessagingConfiguration $channel): array
    {
        Log::info('Sending push notification', [
            'message_id' => $message->id,
            'provider' => $channel->provider,
            'recipient' => $message->recipient,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'push_' . uniqid(),
        ];
    }
}
