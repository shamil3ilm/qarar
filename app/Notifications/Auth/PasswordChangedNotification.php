<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Your Password Has Been Changed')
            ->view('emails.auth.password-changed', [
                'name'     => $notifiable->name,
                'resetUrl' => config('app.url') . '/auth/forgot-password',
            ]);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to'      => $notifiable->phone,
            'message' => 'Your ERP account password was changed. If this was not you, reset it immediately.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'password_changed',
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
