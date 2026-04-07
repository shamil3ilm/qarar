<?php

declare(strict_types=1);

namespace App\Channels;

use App\Services\Core\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(
        private readonly SmsService $smsService
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms');

        if (empty($to)) {
            return;
        }

        if (!method_exists($notification, 'toSms')) {
            return;
        }

        try {
            /** @var array{to: string, message: string} $data */
            $data = $notification->toSms($notifiable);
            $this->smsService->send($data['to'], $data['message']);
        } catch (\Throwable $e) {
            // SMS failures must never break notification delivery for other channels
            \Illuminate\Support\Facades\Log::warning('SmsChannel: delivery failed', [
                'notifiable' => get_class($notifiable),
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
