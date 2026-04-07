<?php

declare(strict_types=1);

namespace App\Notifications\HR;

use App\Models\HR\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payslip $payslip) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Payslip is Ready')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your payslip for the following period is now available.')
            ->line('**Period Start:** ' . $this->payslip->period_start->format('M d, Y'))
            ->line('**Period End:** ' . $this->payslip->period_end->format('M d, Y'))
            ->line("**Net Pay:** {$this->payslip->currency_code} " . number_format((float) $this->payslip->net_pay, 2))
            ->action('View Payslip', config('app.url') . '/hr/payslips/' . $this->payslip->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payroll_processed',
            'payslip_id' => $this->payslip->id,
            'period_start' => $this->payslip->period_start->format('Y-m-d'),
            'period_end' => $this->payslip->period_end->format('Y-m-d'),
            'net_pay' => $this->payslip->net_pay,
        ];
    }
}
