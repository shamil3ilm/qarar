<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BruteForceAlertNotification extends Notification
{
    public function __construct(
        private readonly string $ipAddress,
        private readonly int $attemptCount,
        private readonly string $email
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Security Alert: Multiple Failed Login Attempts')
            ->view('emails.auth.brute-force-alert', [
                'name'          => $notifiable->name,
                'ipAddress'     => $this->ipAddress,
                'attemptCount'  => $this->attemptCount,
                'email'         => $this->email,
                'lockoutMinutes' => 30,
                'secureUrl'     => config('app.url') . '/auth/change-password',
            ]);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to'      => $notifiable->phone,
            'message' => "Security Alert: {$this->attemptCount} failed login attempts detected on your ERP account from IP {$this->ipAddress}. If this was not you, secure your account immediately.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'brute_force_alert',
            'ip_address'    => $this->ipAddress,
            'attempt_count' => $this->attemptCount,
            'email'         => $this->email,
        ];
    }
}
