<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CreditExposure;
use App\Models\Accounting\CreditHold;
use App\Models\Accounting\CreditLimit;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditManagementService
{
    /**
     * Get the current credit exposure for a contact (live calculation).
     */
    public function getCreditExposure(Contact $contact): array
    {
        $openInvoices = Invoice::where('organization_id', $contact->organization_id)
            ->where('customer_id', $contact->id)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->sum('amount_due');

        $openOrders = SalesOrder::where('organization_id', $contact->organization_id)
            ->where('customer_id', $contact->id)
            ->whereIn('status', ['confirmed', 'processing'])
            ->sum('total');

        $totalExposure = bcadd((string) $openInvoices, (string) $openOrders, 4);

        $creditLimit = CreditLimit::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->active()
            ->first();

        $limitAmount     = $creditLimit ? (string) $creditLimit->credit_limit : '0';
        $availableCredit = bcsub($limitAmount, $totalExposure, 4);
        $utilizationPct  = bccomp($limitAmount, '0', 4) > 0
            ? bcmul(bcdiv($totalExposure, $limitAmount, 8), '100', 4)
            : '0.0000';

        return [
            'open_invoices'    => (float) $openInvoices,
            'open_orders'      => (float) $openOrders,
            'total_exposure'   => (float) $totalExposure,
            'credit_limit'     => $limitAmount,
            'available_credit' => (float) $availableCredit,
            'utilization_pct'  => (float) $utilizationPct,
            'currency_code'    => $creditLimit?->currency_code ?? 'SAR',
        ];
    }

    /**
     * Check whether the contact can accept an additional amount within credit limit.
     * Returns true if within limit or no credit limit is configured.
     */
    public function checkCreditLimit(Contact $contact, string|float|int $amount): bool
    {
        $creditLimit = CreditLimit::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->active()
            ->first();

        if (!$creditLimit) {
            return true;
        }

        if ($creditLimit->isBlocked()) {
            return false;
        }

        $exposure        = $this->getCreditExposure($contact);
        $newTotalExposure = bcadd((string) $exposure['total_exposure'], (string) $amount, 4);

        return bccomp($newTotalExposure, (string) $creditLimit->credit_limit, 4) <= 0;
    }

    /**
     * Create or update the credit limit for a contact.
     */
    public function setCreditLimit(Contact $contact, array $data): CreditLimit
    {
        return DB::transaction(function () use ($contact, $data) {
            $existing = CreditLimit::where('organization_id', $contact->organization_id)
                ->where('contact_id', $contact->id)
                ->first();

            $payload = [
                'organization_id'    => $contact->organization_id,
                'contact_id'         => $contact->id,
                'credit_limit'       => $data['credit_limit'],
                'currency_code'      => $data['currency_code'] ?? 'SAR',
                'valid_from'         => $data['valid_from'] ?? now()->toDateString(),
                'valid_until'        => $data['valid_until'] ?? null,
                'payment_terms_days' => $data['payment_terms_days'] ?? 30,
                'risk_class'         => $data['risk_class'] ?? CreditLimit::RISK_MEDIUM,
                'last_reviewed_at'   => now(),
                'reviewed_by'        => auth()->id(),
                'notes'              => $data['notes'] ?? null,
            ];

            if ($existing) {
                $existing->update($payload);
                return $existing->fresh();
            }

            return CreditLimit::create($payload);
        });
    }

    /**
     * Place a credit hold on a contact.
     */
    public function placeHold(Contact $contact, array $data): CreditHold
    {
        // Check for existing active hold
        $existingHold = CreditHold::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->active()
            ->first();

        if ($existingHold) {
            throw new InvalidArgumentException('Contact already has an active credit hold.');
        }

        return CreditHold::create([
            'organization_id' => $contact->organization_id,
            'contact_id'      => $contact->id,
            'held_at'         => now(),
            'hold_reason'     => $data['hold_reason'],
            'held_by'         => auth()->id(),
        ]);
    }

    /**
     * Release an active credit hold.
     */
    public function releaseHold(CreditHold $hold, string $releaseReason): CreditHold
    {
        if (!$hold->isActive()) {
            throw new InvalidArgumentException('This credit hold has already been released.');
        }

        $hold->update([
            'released_at'    => now(),
            'release_reason' => $releaseReason,
            'released_by'    => auth()->id(),
        ]);

        return $hold->fresh();
    }

    /**
     * Snapshot credit exposures for all contacts in an organization on a given date.
     */
    public function snapshotExposures(Organization $organization, Carbon $date): int
    {
        $contacts = Contact::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get();

        $snapshotDate = $date->toDateString();
        $count = 0;

        foreach ($contacts as $contact) {
            $exposure = $this->getCreditExposure($contact);

            if ($exposure['total_exposure'] == 0 && $exposure['credit_limit'] == 0) {
                continue;
            }

            CreditExposure::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'contact_id'      => $contact->id,
                    'snapshot_date'   => $snapshotDate,
                ],
                [
                    'open_invoices'    => $exposure['open_invoices'],
                    'open_orders'      => $exposure['open_orders'],
                    'total_exposure'   => $exposure['total_exposure'],
                    'credit_limit'     => $exposure['credit_limit'],
                    'available_credit' => $exposure['available_credit'],
                    'utilization_pct'  => $exposure['utilization_pct'],
                ]
            );

            $count++;
        }

        return $count;
    }
}
