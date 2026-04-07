<?php

declare(strict_types=1);

namespace App\Queries\HR;

use App\Models\HR\PayrollPeriod;
use App\Queries\Contracts\Query;
use Illuminate\Support\Facades\DB;

/**
 * Returns aggregate statistics for a payroll period.
 *
 * Replaces the in-memory collection aggregation previously in PayrollService::getPeriodSummary().
 *
 * Usage:
 *   $summary = (new GetPayrollPeriodSummaryQuery($periodId, $orgId))->execute();
 */
class GetPayrollPeriodSummaryQuery implements Query
{
    public function __construct(
        private readonly int $periodId,
        private readonly int $organizationId,
    ) {}

    public function execute(): array
    {
        $period = PayrollPeriod::where('organization_id', $this->organizationId)
            ->findOrFail($this->periodId);

        $agg = DB::table('payslips')
            ->where('payroll_period_id', $this->periodId)
            ->where('organization_id', $this->organizationId)
            ->selectRaw("
                COUNT(*) as total_payslips,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                COALESCE(SUM(gross_earnings), 0) as total_gross,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_salary), 0) as total_net
            ")
            ->first();

        return [
            'period'    => [
                'id'           => $period->id,
                'name'         => $period->name,
                'start_date'   => $period->start_date,
                'end_date'     => $period->end_date,
                'payment_date' => $period->payment_date,
                'status'       => $period->status,
            ],
            'payslips'  => [
                'total'    => (int) $agg->total_payslips,
                'paid'     => (int) $agg->paid_count,
                'approved' => (int) $agg->approved_count,
                'pending'  => (int) $agg->pending_count,
                'draft'    => (int) $agg->draft_count,
            ],
            'financials' => [
                'total_gross'      => (float) $agg->total_gross,
                'total_deductions' => (float) $agg->total_deductions,
                'total_net'        => (float) $agg->total_net,
            ],
        ];
    }
}
