<?php

declare(strict_types=1);

namespace App\Notifications\Sales;

use App\Models\Sales\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        // Only store in-app notification for users (not external contacts)
        if ($notifiable instanceof \App\Models\User) {
            $channels[] = 'database';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $displayName = $notifiable->name
            ?? (method_exists($notifiable, 'getDisplayName') ? $notifiable->getDisplayName() : null)
            ?? $notifiable->contact_name
            ?? $notifiable->company_name
            ?? 'Customer';

        $mail = (new MailMessage)
            ->subject("Invoice #{$this->invoice->invoice_number}")
            ->greeting("Hello {$displayName},")
            ->line('An invoice has been sent to you.')
            ->line("**Invoice Number:** #{$this->invoice->invoice_number}")
            ->line("**Amount Due:** {$this->invoice->currency_code} " . number_format((float) $this->invoice->total, 2));

        if (!empty($this->invoice->due_date)) {
            $mail->line('**Due Date:** ' . $this->invoice->due_date->format('M d, Y'));
        }

        $mail->action('View Invoice', config('app.url') . '/invoices/' . $this->invoice->id);

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_sent',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total' => $this->invoice->total,
            'currency' => $this->invoice->currency_code,
        ];
    }
}
