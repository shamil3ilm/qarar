<?php

declare(strict_types=1);

namespace App\Notifications\Sales;

use App\Channels\SmsChannel;
use App\Models\Sales\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invoice $invoice,
        private readonly int $daysOverdue
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
        return (new MailMessage)
            ->subject("Invoice #{$this->invoice->invoice_number} is Overdue")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your invoice is **{$this->daysOverdue} days overdue**.")
            ->line("**Invoice Number:** #{$this->invoice->invoice_number}")
            ->line("**Amount Due:** {$this->invoice->currency_code} " . number_format((float) $this->invoice->total, 2))
            ->action('Pay Now', config('app.url') . '/invoices/' . $this->invoice->id);
    }

    public function toSms(object $notifiable): array
    {
        return [
            'to' => $notifiable->phone,
            'message' => "Invoice #{$this->invoice->invoice_number} is {$this->daysOverdue} days overdue. Amount: {$this->invoice->currency_code} " . number_format((float) $this->invoice->total, 2) . '.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'days_overdue' => $this->daysOverdue,
            'total' => $this->invoice->total,
            'currency' => $this->invoice->currency_code,
        ];
    }
}
