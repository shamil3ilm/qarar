<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnauthorizedAccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $resource,
        private readonly string $ipAddress,
        private readonly string $userAgent
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Security Warning: Unauthorized Access Attempt')
            ->view('emails.auth.unauthorized-access', [
                'name'        => $notifiable->name,
                'resource'    => $this->resource,
                'ipAddress'   => $this->ipAddress,
                'userAgent'   => mb_substr($this->userAgent, 0, 80),
                'attemptTime' => now()->format('M d, Y H:i:s T'),
                'secureUrl'   => config('app.url') . '/auth/change-password',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'unauthorized_access',
            'resource'   => $this->resource,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
