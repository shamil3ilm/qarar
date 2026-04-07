<?php

declare(strict_types=1);

namespace App\Notifications\Sales;

use App\Models\Sales\PaymentReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PaymentReceived $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Received - {$this->payment->reference_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('We have received your payment.')
            ->line("**Amount:** {$this->payment->currency_code} " . number_format((float) $this->payment->amount, 2))
            ->line('**Date:** ' . $this->payment->payment_date->format('M d, Y'))
            ->line("**Reference:** {$this->payment->reference_number}")
            ->action('View Receipt', config('app.url') . '/payments/' . $this->payment->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency_code,
            'reference_number' => $this->payment->reference_number,
        ];
    }
}
