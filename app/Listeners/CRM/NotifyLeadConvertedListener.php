<?php

declare(strict_types=1);

namespace App\Listeners\CRM;

use App\Events\CRM\LeadConverted;
use App\Models\User;
use App\Services\Core\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLeadConvertedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(LeadConverted $event): void
    {
        $lead = $event->lead;
        $contact = $event->contact;

        if (!$lead->assigned_to) {
            return;
        }

        $message = $event->opportunity
            ? "Lead '{$lead->title}' converted to contact and opportunity"
            : "Lead '{$lead->title}' converted to contact '{$contact->getDisplayName()}'";

        $user = User::find($lead->assigned_to);
        if (!$user) {
            return;
        }

        $this->notificationService->send(
            $user,
            'lead_converted',
            $message,
            $message,
            null,
            null,
            null,
            [
                'lead_id' => $lead->id,
                'lead_title' => $lead->title,
                'contact_id' => $contact->id,
                'contact_name' => $contact->getDisplayName(),
                'opportunity_id' => $event->opportunity?->id,
            ]
        );
    }
}
