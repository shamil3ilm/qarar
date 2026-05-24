<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgingReportService
{
    /** Aging buckets in days */
    private const BUCKETS = [
        'current'  => [0, 0],
        '1_30'     => [1, 30],
        '31_60'    => [31, 60],
        '61_90'    => [61, 90],
        '91_120'   => [91, 120],
        'over_120' => [121, PHP_INT_MAX],
    ];

    /**
     * AR Aging: outstanding customer invoices bucketed by days overdue.
     */
    public function getArAging(int $organizationId, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : now();

        $invoices = DB::table('invoices')
            ->join('contacts', 'invoices.customer_id', '=', 'contacts.id')
            ->select(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.customer_id',
                DB::raw("COALESCE(contacts.company_name, contacts.contact_name) as customer_name"),
                'invoices.due_date',
                'invoices.total',
                'invoices.amount_paid',
                DB::raw('(invoices.total - invoices.amount_paid) as outstanding')
            )
            ->where('invoices.organization_id', $organizationId)
            ->whereIn('invoices.status', ['sent', 'partial', 'overdue'])
            ->whereNull('invoices.deleted_at')
            ->whereColumn('invoices.total', '>', 'invoices.amount_paid')
            ->get();

        return $this->buildAgingReport($invoices, $asOf, 'customer_name', 'customer_id');
    }

    /**
     * AP Aging: outstanding vendor bills bucketed by days overdue.
     */
    public function getApAging(int $organizationId, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : now();

        $bills = DB::table('bills')
            ->join('contacts', 'bills.supplier_id', '=', 'contacts.id')
            ->select(
                'bills.id',
                'bills.bill_number',
                'bills.supplier_id',
                DB::raw("COALESCE(contacts.company_name, contacts.contact_name) as vendor_name"),
                'bills.due_date',
                'bills.total',
                'bills.amount_paid',
                DB::raw('(bills.total - bills.amount_paid) as outstanding')
            )
            ->where('bills.organization_id', $organizationId)
            ->whereIn('bills.status', ['approved', 'partial', 'overdue'])
            ->whereNull('bills.deleted_at')
            ->whereColumn('bills.total', '>', 'bills.amount_paid')
            ->get();

        return $this->buildAgingReport($bills, $asOf, 'vendor_name', 'supplier_id');
    }

    private function buildAgingReport(
        Collection $items,
        Carbon $asOf,
        string $nameField,
        string $groupField
    ): array {
        $emptyBuckets = array_fill_keys(array_keys(self::BUCKETS), '0.0000');
        $byContact    = [];
        $totals       = $emptyBuckets;

        foreach ($items as $item) {
            $dueDate     = Carbon::parse($item->due_date);
            $daysOverdue = max(0, (int) $dueDate->diffInDays($asOf, false));
            $outstanding = (string) ($item->outstanding ?? 0);
            $bucket      = $this->resolveBucket($daysOverdue);
            $contactId   = $item->$groupField;

            if (!isset($byContact[$contactId])) {
                $byContact[$contactId] = array_merge(
                    [
                        'contact_id' => $contactId,
                        'name'       => $item->$nameField,
                        'total'      => '0.0000',
                    ],
                    $emptyBuckets
                );
            }

            $byContact[$contactId][$bucket] = bcadd(
                $byContact[$contactId][$bucket],
                $outstanding,
                4
            );
            $byContact[$contactId]['total'] = bcadd(
                $byContact[$contactId]['total'],
                $outstanding,
                4
            );
            $totals[$bucket] = bcadd($totals[$bucket], $outstanding, 4);
        }

        $grandTotal = array_reduce(
            array_values($totals),
            static fn (string $carry, string $v): string => bcadd($carry, $v, 4),
            '0.0000'
        );

        return [
            'as_of_date'    => $asOf->toDateString(),
            'by_contact'    => array_values($byContact),
            'totals'        => $totals,
            'grand_total'   => $grandTotal,
            'bucket_labels' => [
                'current'  => 'Current (not yet due)',
                '1_30'     => '1-30 days',
                '31_60'    => '31-60 days',
                '61_90'    => '61-90 days',
                '91_120'   => '91-120 days',
                'over_120' => 'Over 120 days',
            ],
        ];
    }

    private function resolveBucket(int $daysOverdue): string
    {
        if ($daysOverdue === 0) {
            return 'current';
        }

        foreach (self::BUCKETS as $key => [$min, $max]) {
            if ($daysOverdue >= $min && $daysOverdue <= $max) {
                return $key;
            }
        }

        return 'over_120';
    }
}
