<?php

declare(strict_types=1);

namespace App\Notifications\Fraud;

use App\Models\Fraud\FraudAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FraudAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FraudAlert $alert,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ruleName    = $this->alert->rule?->name ?? 'Unknown rule';
        $severity    = strtoupper($this->alert->severity);
        $entityType  = $this->alert->entity_type;
        $entityId    = $this->alert->entity_id;
        $fraudScore  = $this->alert->fraud_score;
        $createdAt   = $this->alert->created_at?->toDateTimeString() ?? now()->toDateTimeString();

        return (new MailMessage)
            ->subject("Fraud Alert: {$severity} - {$ruleName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A **{$severity}** fraud alert has been triggered in your organization.")
            ->line("**Rule:** {$ruleName}")
            ->line("**Entity:** {$entityType} #{$entityId}")
            ->line("**Fraud Score:** {$fraudScore}")
            ->line("**Detected at:** {$createdAt}")
            ->line("**Status:** {$this->alert->status}")
            ->action('Review Alert', url("/fraud/alerts/{$this->alert->id}"))
            ->line('Please review and take appropriate action.')
            ->line('If this is a false positive, you can mark it as resolved in the system.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'fraud_alert',
            'alert_id'    => $this->alert->id,
            'alert_uuid'  => $this->alert->uuid,
            'severity'    => $this->alert->severity,
            'entity_type' => $this->alert->entity_type,
            'entity_id'   => $this->alert->entity_id,
            'fraud_score' => $this->alert->fraud_score,
            'rule_name'   => $this->alert->rule?->name,
            'status'      => $this->alert->status,
        ];
    }
}
