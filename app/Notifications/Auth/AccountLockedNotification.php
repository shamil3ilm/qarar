<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification
{
    public function __construct(
        private readonly string $ipAddress,
        private readonly int $lockoutMinutes = 30
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
            ->subject('Security Alert: Your Account Has Been Locked')
            ->view('emails.auth.account-locked', [
                'name'           => $notifiable->name,
                'ipAddress'      => $this->ipAddress,
                'lockoutMinutes' => $this->lockoutMinutes,
                'unlockUrl'      => config('app.url') . '/auth/forgot-password',
            ]);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to'      => $notifiable->phone,
            'message' => "Your ERP account has been temporarily locked for {$this->lockoutMinutes} minutes due to multiple failed login attempts from IP {$this->ipAddress}. Contact support if you need immediate assistance.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'account_locked',
            'ip_address'   => $this->ipAddress,
            'locked_until' => now()->addMinutes($this->lockoutMinutes)->toIso8601String(),
        ];
    }
}
