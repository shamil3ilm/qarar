<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginFromNewDeviceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private readonly string $location = ''
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
            ->subject('New Device Login Detected')
            ->view('emails.auth.login-new-device', [
                'name'       => $notifiable->name,
                'ipAddress'  => $this->ipAddress,
                'userAgent'  => mb_substr($this->userAgent, 0, 80),
                'loginTime'  => now()->format('M d, Y H:i:s T'),
                'location'   => $this->location ?: null,
                'secureUrl'  => config('app.url') . '/auth/change-password',
            ]);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to'      => $notifiable->phone,
            'message' => "New login to your ERP account from {$this->ipAddress}. Not you? Secure your account immediately.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'login_new_device',
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
