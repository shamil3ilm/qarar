<?php

declare(strict_types=1);

namespace App\Queries\Accounting;

use App\Queries\Contracts\Query;
use Illuminate\Support\Facades\DB;

/**
 * AR Aging report query — groups open invoices into aging buckets.
 *
 * Buckets: current (not yet due), 1–30, 31–60, 61–90, 90+ days overdue.
 *
 * Usage:
 *   $aging = (new GetArAgingQuery($orgId))->execute();
 */
class GetArAgingQuery implements Query
{
    public function __construct(
        private readonly int     $organizationId,
        private readonly ?string $asOfDate = null,
    ) {}

    public function execute(): array
    {
        $asOf = $this->asOfDate ?? now()->toDateString();

        $rows = DB::table('invoices as inv')
            ->join('contacts as c', 'inv.customer_id', '=', 'c.id')
            ->where('inv.organization_id', $this->organizationId)
            ->whereIn('inv.status', ['sent', 'partial', 'overdue'])
            ->where('inv.amount_due', '>', 0)
            ->where('inv.invoice_date', '<=', $asOf)
            ->selectRaw("
                c.id as customer_id,
                c.name as customer_name,
                SUM(CASE WHEN inv.due_date >= ? THEN inv.amount_due ELSE 0 END) as current_amount,
                SUM(CASE WHEN inv.due_date < ? AND inv.due_date >= DATE_SUB(?, INTERVAL 30 DAY) THEN inv.amount_due ELSE 0 END) as days_1_30,
                SUM(CASE WHEN inv.due_date < DATE_SUB(?, INTERVAL 30 DAY) AND inv.due_date >= DATE_SUB(?, INTERVAL 60 DAY) THEN inv.amount_due ELSE 0 END) as days_31_60,
                SUM(CASE WHEN inv.due_date < DATE_SUB(?, INTERVAL 60 DAY) AND inv.due_date >= DATE_SUB(?, INTERVAL 90 DAY) THEN inv.amount_due ELSE 0 END) as days_61_90,
                SUM(CASE WHEN inv.due_date < DATE_SUB(?, INTERVAL 90 DAY) THEN inv.amount_due ELSE 0 END) as days_90_plus,
                SUM(inv.amount_due) as total_outstanding,
                COUNT(inv.id) as invoice_count
            ", [$asOf, $asOf, $asOf, $asOf, $asOf, $asOf, $asOf, $asOf])
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_outstanding')
            ->get();

        $totals = [
            'current'      => (float) $rows->sum('current_amount'),
            'days_1_30'    => (float) $rows->sum('days_1_30'),
            'days_31_60'   => (float) $rows->sum('days_31_60'),
            'days_61_90'   => (float) $rows->sum('days_61_90'),
            'days_90_plus' => (float) $rows->sum('days_90_plus'),
            'total'        => (float) $rows->sum('total_outstanding'),
        ];

        return [
            'as_of_date' => $asOf,
            'customers'  => $rows->map(fn($r) => (array) $r)->values()->all(),
            'totals'     => $totals,
        ];
    }
}
