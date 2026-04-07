<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Opportunity;
use App\Models\CRM\ServiceTicket;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;

class CustomerProfileService
{
    /**
     * Return a consolidated 360° view of a customer (contact).
     *
     * @return array<string, mixed>
     */
    public function getProfile(int $organizationId, int $contactId): array
    {
        $contact = Contact::where('organization_id', $organizationId)->findOrFail($contactId);

        $invoiceSummary = $this->getInvoiceSummary($organizationId, $contactId);

        // Opportunities linked directly to this contact
        $opportunitiesQuery = Opportunity::where('organization_id', $organizationId)
            ->where('contact_id', $contactId);

        $recentOpportunities = (clone $opportunitiesQuery)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'status', 'amount', 'probability', 'expected_close_date']);

        $openOpportunityValue = (clone $opportunitiesQuery)
            ->where('status', Opportunity::STATUS_OPEN)
            ->sum('amount');

        $totalOpportunities = (clone $opportunitiesQuery)->count();

        // Activities linked polymorphically to this contact (related_type = contact, related_id = contactId)
        $activitiesQuery = Activity::where('organization_id', $organizationId)
            ->where('related_type', 'contact')
            ->where('related_id', $contactId);

        $recentActivities = (clone $activitiesQuery)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'activity_type', 'subject', 'status', 'start_datetime', 'end_datetime', 'created_at']);

        // Service tickets linked to this contact
        $ticketsQuery = ServiceTicket::where('organization_id', $organizationId)
            ->where('contact_id', $contactId);

        $recentTickets = (clone $ticketsQuery)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'created_at']);

        $openTicketCount = (clone $ticketsQuery)
            ->whereIn('status', [
                ServiceTicket::STATUS_OPEN,
                ServiceTicket::STATUS_IN_PROGRESS,
                ServiceTicket::STATUS_PENDING_CUSTOMER,
            ])
            ->count();

        return [
            'contact'         => $contact,
            'financial'       => $invoiceSummary,
            'opportunities'   => [
                'total'      => $totalOpportunities,
                'open_value' => $openOpportunityValue,
                'recent'     => $recentOpportunities,
            ],
            'activities'      => [
                'total'  => (clone $activitiesQuery)->count(),
                'recent' => $recentActivities,
            ],
            'service_tickets' => [
                'total'  => (clone $ticketsQuery)->count(),
                'open'   => $openTicketCount,
                'recent' => $recentTickets,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getInvoiceSummary(int $organizationId, int $contactId): array
    {
        $invoices = Invoice::where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->whereNull('deleted_at')
            ->get(['total_amount', 'amount_paid', 'status']);

        $totalRevenue = (float) $invoices->sum('total_amount');
        $totalPaid    = (float) $invoices->sum('amount_paid');
        $outstanding  = bcsub((string) $totalRevenue, (string) $totalPaid, 4);

        return [
            'total_invoiced'    => $totalRevenue,
            'total_paid'        => $totalPaid,
            'total_outstanding' => $outstanding,
            'invoice_count'     => $invoices->count(),
            'overdue_count'     => $invoices->where('status', 'overdue')->count(),
        ];
    }
}
