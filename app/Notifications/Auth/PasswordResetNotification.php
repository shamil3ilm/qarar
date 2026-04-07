<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (!empty($notifiable->phone) && config('sms.driver') !== 'log') {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Password Reset Code')
            ->view('emails.auth.password-reset', [
                'name' => $notifiable->name,
                'code' => $this->code,
            ]);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to'      => $notifiable->phone,
            'message' => "Your ERP password reset code: {$this->code}. Valid for 60 minutes. If you didn't request this, ignore this message.",
        ];
    }
}
