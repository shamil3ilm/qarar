<?php

declare(strict_types=1);

namespace App\Notifications\Core;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalPasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Reset Your Customer Portal Password')
            ->view('emails.core.portal-password-reset', [
                'email' => $notifiable->email,
                'token' => $this->token,
            ]);
    }
}
