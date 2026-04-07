<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\Payslip;
use Illuminate\Support\Facades\DB;

class HRReportService
{
    protected int $organizationId;
    protected ?int $branchId = null;

    public function setContext(int $organizationId, ?int $branchId = null): self
    {
        $this->organizationId = $organizationId;
        $this->branchId = $branchId;
        return $this;
    }

    /**
     * Generate Headcount Report.
     */
    public function generateHeadcountReport(string $asOfDate, ?int $departmentId = null): array
    {
        $query = Employee::where('organization_id', $this->organizationId)
            ->where('date_of_joining', '<=', $asOfDate)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('date_of_exit')
                    ->orWhere('date_of_exit', '>', $asOfDate);
            });

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        // All sections resolved via DB aggregation — no full collection loaded into memory.

        // Summary counts
        $summary = (clone $query)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
            SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
            SUM(CASE WHEN employment_type = 'permanent' THEN 1 ELSE 0 END) as permanent,
            SUM(CASE WHEN employment_type = 'contract' THEN 1 ELSE 0 END) as contract,
            SUM(CASE WHEN employment_type = 'probation' THEN 1 ELSE 0 END) as probation,
            AVG(TIMESTAMPDIFF(YEAR, date_of_joining, ?)) as avg_tenure,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth,   ?)) as avg_age
        ", [$asOfDate, $asOfDate])->first();

        // By department
        $byDepartment = (clone $query)
            ->join('departments', 'departments.id', '=', 'employees.department_id', 'left')
            ->selectRaw("
                employees.department_id,
                COALESCE(departments.name, 'Unassigned') as department_name,
                COUNT(*) as count,
                SUM(CASE WHEN employees.gender = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN employees.gender = 'female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN employees.employment_type = 'permanent' THEN 1 ELSE 0 END) as permanent,
                SUM(CASE WHEN employees.employment_type = 'contract' THEN 1 ELSE 0 END) as contract,
                SUM(CASE WHEN employees.employment_type = 'probation' THEN 1 ELSE 0 END) as probation
            ")
            ->groupBy('employees.department_id', 'departments.name')
            ->get()->toArray();

        // By designation (top 10)
        $byDesignation = (clone $query)
            ->join('designations', 'designations.id', '=', 'employees.designation_id', 'left')
            ->selectRaw("employees.designation_id, COALESCE(designations.name, 'Unassigned') as designation_name, COUNT(*) as count")
            ->groupBy('employees.designation_id', 'designations.name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()->toArray();

        // By tenure
        $tenureRows = (clone $query)->selectRaw("
            CASE
                WHEN TIMESTAMPDIFF(YEAR, date_of_joining, ?) < 1  THEN '0_1_year'
                WHEN TIMESTAMPDIFF(YEAR, date_of_joining, ?) < 3  THEN '1_3_years'
                WHEN TIMESTAMPDIFF(YEAR, date_of_joining, ?) < 5  THEN '3_5_years'
                WHEN TIMESTAMPDIFF(YEAR, date_of_joining, ?) < 10 THEN '5_10_years'
                ELSE '10_plus_years'
            END as bracket, COUNT(*) as cnt
        ", [$asOfDate, $asOfDate, $asOfDate, $asOfDate])->groupBy('bracket')->pluck('cnt', 'bracket');

        $byTenure = [
            '0_1_year'     => (int) ($tenureRows['0_1_year']     ?? 0),
            '1_3_years'    => (int) ($tenureRows['1_3_years']    ?? 0),
            '3_5_years'    => (int) ($tenureRows['3_5_years']    ?? 0),
            '5_10_years'   => (int) ($tenureRows['5_10_years']   ?? 0),
            '10_plus_years'=> (int) ($tenureRows['10_plus_years']?? 0),
        ];

        // By age
        $ageRows = (clone $query)->selectRaw("
            CASE
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) < 25 THEN 'under_25'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) < 35 THEN '25_35'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) < 45 THEN '35_45'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, NOW()) < 55 THEN '45_55'
                ELSE '55_plus'
            END as bracket, COUNT(*) as cnt
        ")->groupBy('bracket')->pluck('cnt', 'bracket');

        $byAge = [
            'under_25' => (int) ($ageRows['under_25'] ?? 0),
            '25_35'    => (int) ($ageRows['25_35']    ?? 0),
            '35_45'    => (int) ($ageRows['35_45']    ?? 0),
            '45_55'    => (int) ($ageRows['45_55']    ?? 0),
            '55_plus'  => (int) ($ageRows['55_plus']  ?? 0),
        ];

        // By nationality (top 10)
        $byNationality = (clone $query)
            ->selectRaw("COALESCE(nationality, 'Unknown') as nationality, COUNT(*) as count")
            ->groupBy('nationality')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()->toArray();

        return [
            'report_type' => 'headcount',
            'as_of_date'  => $asOfDate,
            'summary' => [
                'total_headcount'      => (int)   ($summary->total     ?? 0),
                'male'                 => (int)   ($summary->male      ?? 0),
                'female'               => (int)   ($summary->female    ?? 0),
                'permanent'            => (int)   ($summary->permanent ?? 0),
                'contract'             => (int)   ($summary->contract  ?? 0),
                'probation'            => (int)   ($summary->probation ?? 0),
                'average_tenure_years' => round((float) ($summary->avg_tenure ?? 0), 1),
                'average_age'          => round((float) ($summary->avg_age    ?? 0), 1),
            ],
            'by_department'  => $byDepartment,
            'by_designation' => $byDesignation,
            'by_tenure'      => $byTenure,
            'by_age'         => $byAge,
            'by_nationality' => $byNationality,
            'generated_at'   => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Employee Turnover Report.
     */
    public function generateTurnoverReport(string $startDate, string $endDate): array
    {
        // Starting headcount
        $startingCount = Employee::where('organization_id', $this->organizationId)
            ->where('date_of_joining', '<', $startDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('date_of_exit')
                    ->orWhere('date_of_exit', '>=', $startDate);
            })
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        // New hires
        $newHires = Employee::where('organization_id', $this->organizationId)
            ->whereBetween('date_of_joining', [$startDate, $endDate])
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->with('department')
            ->get();

        // Separations
        $separations = Employee::where('organization_id', $this->organizationId)
            ->whereBetween('date_of_exit', [$startDate, $endDate])
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->with('department')
            ->get();

        // Ending headcount
        $endingCount = Employee::where('organization_id', $this->organizationId)
            ->where('date_of_joining', '<=', $endDate)
            ->where(function ($q) use ($endDate) {
                $q->whereNull('date_of_exit')
                    ->orWhere('date_of_exit', '>', $endDate);
            })
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        // Calculate rates
        $averageHeadcount = ($startingCount + $endingCount) / 2;
        $turnoverRate = $averageHeadcount > 0 ? ($separations->count() / $averageHeadcount) * 100 : 0;
        $attritionRate = $averageHeadcount > 0
            ? ($separations->where('exit_type', 'resignation')->count() / $averageHeadcount) * 100
            : 0;

        // New hires by department
        $hiresByDept = $newHires->groupBy('department_id')->map(function ($group) {
            return [
                'department' => $group->first()->department?->name ?? 'Unassigned',
                'count' => $group->count(),
            ];
        })->sortByDesc('count')->values()->toArray();

        // Separations by reason
        $separationsByReason = $separations->groupBy('exit_type')->map(function ($group, $type) {
            return [
                'reason' => ucfirst(str_replace('_', ' ', $type ?: 'Unknown')),
                'count' => $group->count(),
            ];
        })->values()->toArray();

        // Separations by tenure
        $separationsByTenure = [
            '0_6_months' => $separations->filter(fn($e) => $e->getTenureInMonths() < 6)->count(),
            '6_12_months' => $separations->filter(fn($e) => $e->getTenureInMonths() >= 6 && $e->getTenureInMonths() < 12)->count(),
            '1_2_years' => $separations->filter(fn($e) => $e->getTenureInYears() >= 1 && $e->getTenureInYears() < 2)->count(),
            '2_5_years' => $separations->filter(fn($e) => $e->getTenureInYears() >= 2 && $e->getTenureInYears() < 5)->count(),
            '5_plus_years' => $separations->filter(fn($e) => $e->getTenureInYears() >= 5)->count(),
        ];

        return [
            'report_type' => 'turnover',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'summary' => [
                'starting_headcount' => $startingCount,
                'new_hires' => $newHires->count(),
                'separations' => $separations->count(),
                'ending_headcount' => $endingCount,
                'net_change' => $newHires->count() - $separations->count(),
                'turnover_rate' => round($turnoverRate, 2),
                'attrition_rate' => round($attritionRate, 2),
                'retention_rate' => round(100 - $turnoverRate, 2),
            ],
            'new_hires_by_department' => $hiresByDept,
            'separations_by_reason' => $separationsByReason,
            'separations_by_tenure' => $separationsByTenure,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Attendance Summary Report.
     */
    public function generateAttendanceReport(string $startDate, string $endDate, ?int $departmentId = null): array
    {
        $query = Attendance::where('organization_id', $this->organizationId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($this->branchId) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $this->branchId));
        }

        if ($departmentId) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        // All sections resolved via DB aggregation — no full collection loaded.

        // Overall summary
        $summaryRow = (clone $query)->selectRaw("
            COUNT(*) as total_records,
            SUM(CASE WHEN status IN ('present','late','early_leave') THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
            SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day,
            COALESCE(SUM(total_working_hours), 0) as total_working_hours,
            COALESCE(SUM(overtime_hours), 0) as total_overtime_hours,
            COALESCE(AVG(total_working_hours), 0) as average_working_hours
        ")->first();

        $summary = [
            'total_records'         => (int)   ($summaryRow->total_records         ?? 0),
            'present'               => (int)   ($summaryRow->present               ?? 0),
            'absent'                => (int)   ($summaryRow->absent                ?? 0),
            'late'                  => (int)   ($summaryRow->late                  ?? 0),
            'on_leave'              => (int)   ($summaryRow->on_leave              ?? 0),
            'half_day'              => (int)   ($summaryRow->half_day              ?? 0),
            'total_working_hours'   => round((float) ($summaryRow->total_working_hours   ?? 0), 2),
            'total_overtime_hours'  => round((float) ($summaryRow->total_overtime_hours  ?? 0), 2),
            'average_working_hours' => round((float) ($summaryRow->average_working_hours ?? 0), 2),
        ];

        // By department
        $byDepartment = (clone $query)
            ->join('employees as emp', 'emp.id', '=', 'attendances.employee_id')
            ->join('departments', 'departments.id', '=', 'emp.department_id', 'left')
            ->selectRaw("
                COALESCE(departments.name, 'Unassigned') as department,
                COUNT(*) as total_records,
                SUM(CASE WHEN attendances.status IN ('present','late','early_leave') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late
            ")
            ->groupBy('emp.department_id', 'departments.name')
            ->orderByRaw('present / NULLIF(COUNT(*), 0) DESC')
            ->get()
            ->map(fn($r) => [
                'department'      => $r->department,
                'total_records'   => (int) $r->total_records,
                'present'         => (int) $r->present,
                'absent'          => (int) $r->absent,
                'late'            => (int) $r->late,
                'attendance_rate' => $r->total_records > 0
                    ? round(($r->present / $r->total_records) * 100, 2) : 0,
            ])->toArray();

        // By day of week
        $byDayOfWeek = (clone $query)
            ->selectRaw("
                DAYNAME(attendance_date) as day,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('present','late','early_leave') THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
            ")
            ->groupBy('day')
            ->get()->toArray();

        // Top 10 late arrivals
        $lateByEmployee = (clone $query)
            ->where('attendances.status', 'late')
            ->join('employees as emp2', 'emp2.id', '=', 'attendances.employee_id')
            ->selectRaw("CONCAT(emp2.first_name, ' ', emp2.last_name) as employee, COUNT(*) as late_count")
            ->groupBy('attendances.employee_id', 'emp2.first_name', 'emp2.last_name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()->toArray();

        return [
            'report_type' => 'attendance',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'summary' => $summary,
            'by_department' => $byDepartment,
            'by_day_of_week' => $byDayOfWeek,
            'top_late_arrivals' => $lateByEmployee,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Leave Analysis Report.
     */
    public function generateLeaveReport(string $startDate, string $endDate, ?int $departmentId = null): array
    {
        $query = LeaveRequest::where('organization_id', $this->organizationId)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with(['employee.department', 'leaveType']);

        if ($this->branchId) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $this->branchId));
        }

        if ($departmentId) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        // All sections resolved via DB aggregation — no full collection loaded.

        // Summary
        $summaryRow = (clone $query)->selectRaw("
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(total_days), 0) as total_days_requested,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN total_days ELSE 0 END), 0) as total_days_approved
        ")->first();

        $summary = [
            'total_requests'      => (int)   ($summaryRow->total_requests      ?? 0),
            'approved'            => (int)   ($summaryRow->approved            ?? 0),
            'pending'             => (int)   ($summaryRow->pending             ?? 0),
            'rejected'            => (int)   ($summaryRow->rejected            ?? 0),
            'cancelled'           => (int)   ($summaryRow->cancelled           ?? 0),
            'total_days_requested'=> (float) ($summaryRow->total_days_requested?? 0),
            'total_days_approved' => (float) ($summaryRow->total_days_approved ?? 0),
        ];

        // By leave type
        $byLeaveType = (clone $query)
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id', 'left')
            ->selectRaw("
                COALESCE(leave_types.name, 'Unknown') as leave_type,
                COUNT(*) as requests,
                COALESCE(SUM(leave_requests.total_days), 0) as days,
                SUM(CASE WHEN leave_requests.status = 'approved' THEN 1 ELSE 0 END) as approved
            ")
            ->groupBy('leave_requests.leave_type_id', 'leave_types.name')
            ->orderByRaw('COUNT(*) DESC')
            ->get()->toArray();

        // By department
        $byDepartment = (clone $query)
            ->join('employees as emp', 'emp.id', '=', 'leave_requests.employee_id')
            ->join('departments', 'departments.id', '=', 'emp.department_id', 'left')
            ->selectRaw("
                COALESCE(departments.name, 'Unassigned') as department,
                COUNT(*) as requests,
                COALESCE(SUM(leave_requests.total_days), 0) as days
            ")
            ->groupBy('emp.department_id', 'departments.name')
            ->orderByRaw('COUNT(*) DESC')
            ->get()->toArray();

        // By month
        $byMonth = (clone $query)
            ->selectRaw("DATE_FORMAT(start_date, '%Y-%m') as month, COUNT(*) as requests, COALESCE(SUM(total_days), 0) as days")
            ->groupBy('month')
            ->orderBy('month')
            ->get()->toArray();

        // Top 10 leave takers (approved)
        $topLeaveTakers = (clone $query)
            ->where('leave_requests.status', 'approved')
            ->join('employees as emp3', 'emp3.id', '=', 'leave_requests.employee_id')
            ->selectRaw("CONCAT(emp3.first_name, ' ', emp3.last_name) as employee, COUNT(*) as leaves, COALESCE(SUM(leave_requests.total_days), 0) as days")
            ->groupBy('leave_requests.employee_id', 'emp3.first_name', 'emp3.last_name')
            ->orderByRaw('SUM(leave_requests.total_days) DESC')
            ->limit(10)
            ->get()->toArray();

        return [
            'report_type' => 'leave_analysis',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'summary' => $summary,
            'by_leave_type' => $byLeaveType,
            'by_department' => $byDepartment,
            'by_month' => $byMonth,
            'top_leave_takers' => $topLeaveTakers,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Payroll Summary Report.
     */
    public function generatePayrollReport(string $startDate, string $endDate): array
    {
        $query = Payslip::where('organization_id', $this->organizationId)
            ->whereHas('payrollPeriod', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->with(['employee.department', 'items.salaryComponent', 'payrollPeriod']);

        if ($this->branchId) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $this->branchId));
        }

        // All sections resolved via DB aggregation — no full collection loaded.

        // Currency (single lightweight query)
        $currency = (clone $query)->value('currency_code') ?? 'USD';

        // Summary
        $summaryRow = (clone $query)->selectRaw("
            COUNT(*) as total_payslips,
            COALESCE(SUM(gross_earnings), 0) as total_gross,
            COALESCE(SUM(total_deductions), 0) as total_deductions,
            COALESCE(SUM(net_salary), 0) as total_net,
            COALESCE(AVG(gross_earnings), 0) as average_gross,
            COALESCE(AVG(net_salary), 0) as average_net,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status IN ('draft','pending','approved') THEN 1 ELSE 0 END) as pending_count
        ")->first();

        $summary = [
            'total_payslips'  => (int)   ($summaryRow->total_payslips  ?? 0),
            'total_gross'     => (float) ($summaryRow->total_gross      ?? 0),
            'total_deductions'=> (float) ($summaryRow->total_deductions ?? 0),
            'total_net'       => (float) ($summaryRow->total_net        ?? 0),
            'average_gross'   => round((float) ($summaryRow->average_gross ?? 0), 2),
            'average_net'     => round((float) ($summaryRow->average_net   ?? 0), 2),
            'paid_count'      => (int)   ($summaryRow->paid_count       ?? 0),
            'pending_count'   => (int)   ($summaryRow->pending_count    ?? 0),
        ];

        // By department
        $byDepartment = (clone $query)
            ->join('employees as emp', 'emp.id', '=', 'payslips.employee_id')
            ->join('departments', 'departments.id', '=', 'emp.department_id', 'left')
            ->selectRaw("
                COALESCE(departments.name, 'Unassigned') as department,
                COUNT(*) as employees,
                COALESCE(SUM(payslips.gross_earnings), 0) as gross,
                COALESCE(SUM(payslips.total_deductions), 0) as deductions,
                COALESCE(SUM(payslips.net_salary), 0) as net
            ")
            ->groupBy('emp.department_id', 'departments.name')
            ->orderByRaw('SUM(payslips.gross_earnings) DESC')
            ->get()->toArray();

        // By salary component (via payslip items join)
        $byComponent = \Illuminate\Support\Facades\DB::table('payslip_items')
            ->join('payslips', 'payslips.id', '=', 'payslip_items.payslip_id')
            ->whereIn('payslips.id', (clone $query)->select('id'))
            ->selectRaw("
                COALESCE(payslip_items.name, 'Unknown') as name,
                payslip_items.type,
                COALESCE(SUM(payslip_items.amount), 0) as total,
                COUNT(*) as count
            ")
            ->groupBy('payslip_items.name', 'payslip_items.type')
            ->orderByRaw('SUM(payslip_items.amount) DESC')
            ->get()->toArray();

        // Monthly trend
        $byMonth = (clone $query)
            ->join('payroll_periods as pp', 'pp.id', '=', 'payslips.payroll_period_id')
            ->selectRaw("
                DATE_FORMAT(pp.start_date, '%Y-%m') as month,
                COUNT(*) as employees,
                COALESCE(SUM(payslips.gross_earnings), 0) as gross,
                COALESCE(SUM(payslips.net_salary), 0) as net
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get()->toArray();

        return [
            'report_type' => 'payroll_summary',
            'period_start' => $startDate,
            'period_end'   => $endDate,
            'currency'     => $currency,
            'summary'      => $summary,
            'by_department'=> $byDepartment,
            'by_component' => $byComponent,
            'monthly_trend'=> $byMonth,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate HR Dashboard Summary.
     */
    public function getDashboardSummary(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        // Active employees
        $activeEmployees = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active')
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        // Today's attendance
        $todayAttendance = Attendance::where('organization_id', $this->organizationId)
            ->where('attendance_date', $today)
            ->when($this->branchId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('branch_id', $this->branchId)))
            ->get();

        // Pending leave requests
        $pendingLeaves = LeaveRequest::where('organization_id', $this->organizationId)
            ->where('status', 'pending')
            ->when($this->branchId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('branch_id', $this->branchId)))
            ->count();

        // Employees on leave today
        $onLeaveToday = LeaveRequest::where('organization_id', $this->organizationId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->when($this->branchId, fn($q) => $q->whereHas('employee', fn($e) => $e->where('branch_id', $this->branchId)))
            ->count();

        // New joiners this month
        $newJoiners = Employee::where('organization_id', $this->organizationId)
            ->whereBetween('date_of_joining', [$monthStart, $monthEnd])
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        // Expiring documents (next 30 days)
        $expiringDocs = DB::table('employee_documents as ed')
            ->join('employees as e', 'ed.employee_id', '=', 'e.id')
            ->where('e.organization_id', $this->organizationId)
            ->where('e.employment_status', 'active')
            ->whereBetween('ed.expiry_date', [$today, now()->addDays(30)->toDateString()])
            ->count();

        // Birthdays this month
        $birthdays = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active')
            ->whereMonth('date_of_birth', now()->month)
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        // Work anniversaries this month
        $anniversaries = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active')
            ->whereMonth('date_of_joining', now()->month)
            ->whereYear('date_of_joining', '<', now()->year)
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->count();

        return [
            'active_employees' => $activeEmployees,
            'attendance_today' => [
                'present' => $todayAttendance->whereIn('status', ['present', 'late'])->count(),
                'absent' => $todayAttendance->where('status', 'absent')->count(),
                'late' => $todayAttendance->where('status', 'late')->count(),
                'on_leave' => $onLeaveToday,
            ],
            'pending_leave_requests' => $pendingLeaves,
            'new_joiners_this_month' => $newJoiners,
            'expiring_documents' => $expiringDocs,
            'birthdays_this_month' => $birthdays,
            'anniversaries_this_month' => $anniversaries,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
