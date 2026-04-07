<?php

declare(strict_types=1);

namespace App\Notifications\HR;

use App\Models\HR\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly LeaveRequest $leaveRequest,
        private readonly string $reason = ''
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $leaveType = $this->leaveRequest->leaveType;

        $mail = (new MailMessage)
            ->subject('Leave Request Rejected: ' . ($leaveType?->name ?? 'Leave'))
            ->greeting("Hello {$notifiable->name},")
            ->line('Unfortunately, your leave request has been **rejected**.')
            ->line('**Leave Type:** ' . ($leaveType?->name ?? 'N/A'))
            ->line('**From:** ' . $this->leaveRequest->start_date->format('M d, Y'))
            ->line('**To:** ' . $this->leaveRequest->end_date->format('M d, Y'))
            ->line("**Total Days:** {$this->leaveRequest->total_days}");

        if (!empty($this->reason)) {
            $mail->line("**Rejection Reason:** {$this->reason}");
        }

        $mail->action('View Details', url("/hr/leave-requests/{$this->leaveRequest->id}"));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_request_rejected',
            'leave_request_id' => $this->leaveRequest->id,
            'leave_type' => $this->leaveRequest->leaveType?->name,
            'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
            'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            'total_days' => $this->leaveRequest->total_days,
            'reason' => $this->reason,
        ];
    }
}
