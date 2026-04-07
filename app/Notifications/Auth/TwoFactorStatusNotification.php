<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly bool $enabled) {}

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
        if ($this->enabled) {
            return (new MailMessage())
                ->subject('Two-factor authentication enabled')
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('Two-factor authentication has been enabled on your account.')
                ->line('Your account is now more secure.');
        }

        return (new MailMessage())
            ->subject('Two-factor authentication disabled')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Two-factor authentication has been disabled on your account.')
            ->line('If you did not make this change, please secure your account immediately.')
            ->action('Secure My Account', config('app.url') . '/auth/change-password');
    }

    public function toSms(object $notifiable): array
    {
        $status = $this->enabled ? 'enabled' : 'disabled';

        return [
            'to'      => $notifiable->phone,
            'message' => "2FA has been {$status} on your ERP account.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->enabled ? 'two_factor_enabled' : 'two_factor_disabled',
        ];
    }
}
