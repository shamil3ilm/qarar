<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $organizationName) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Welcome to ' . $this->organizationName . '!')
            ->view('emails.auth.welcome', [
                'name'             => $notifiable->name,
                'organizationName' => $this->organizationName,
                'getStartedUrl'    => config('app.url'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'welcome',
            'organization_name' => $this->organizationName,
        ];
    }
}
