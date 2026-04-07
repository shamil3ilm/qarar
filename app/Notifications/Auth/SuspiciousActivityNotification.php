<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $activityType,
        private readonly array $details,
        private readonly string $ipAddress
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (!empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Security Alert: Suspicious Activity Detected')
            ->view('emails.auth.suspicious-activity', [
                'name'         => $notifiable->name,
                'activityType' => $this->activityType,
                'details'      => $this->details,
                'ipAddress'    => $this->ipAddress,
                'secureUrl'    => config('app.url') . '/auth/change-password',
            ]);
    }

    public function toSms(object $notifiable): array
    {
        $label = $this->getActivityLabel();

        return [
            'to'      => $notifiable->phone,
            'message' => "Security Alert: Suspicious activity ({$label}) detected on your ERP account from IP {$this->ipAddress}. If this was not you, secure your account immediately.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'suspicious_activity',
            'activity_type' => $this->activityType,
            'details'       => $this->details,
            'ip_address'    => $this->ipAddress,
        ];
    }

    private function getActivityLabel(): string
    {
        return match ($this->activityType) {
            'concurrent_sessions' => 'Concurrent Sessions',
            'geo_anomaly'         => 'Geographic Anomaly',
            'unusual_time'        => 'Login at Unusual Time',
            'rapid_requests'      => 'Rapid Repeated Requests',
            'privilege_escalation' => 'Privilege Escalation Attempt',
            default               => ucwords(str_replace('_', ' ', $this->activityType)),
        };
    }
}
