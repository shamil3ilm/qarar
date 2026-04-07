<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Core\UserEvent;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeLoan;
use App\Models\HR\LeaveRequest;
use App\Models\HR\Payslip;
use App\Models\HR\PayslipItem;
use App\Models\HR\PayrollPeriod;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Core\UserEventService;
use App\Models\Concerns\ChecksIdempotency;
use App\Traits\StructuredLogger;
use Illuminate\Support\Facades\DB;

/**
 * Manages payroll period processing and payslip lifecycle for all active employees.
 *
 * Responsibilities:
 * - Create and manage payroll periods (open → processing → processed → closed)
 * - Generate payslips for all active employees with a current salary assignment
 * - Calculate earnings and deductions from salary structures with pro-rata adjustments
 *   for unpaid leave using fixed-precision arithmetic (bcmath)
 * - Append statutory deductions (GOSI, EPF, ESI, etc.) via StatutoryDeductionService,
 *   skipping any codes already covered by the employee's salary structure
 * - Deduct loan EMIs by linking PayslipItems to individual loan schedule entries
 * - Transition payslips through states: draft → pending → approved → paid
 * - Post double-entry journal entries (salary expense debit, salary payable and
 *   statutory account credits) when a payslip is marked as paid
 *
 * Side Effects:
 * - Writes Payslip and PayslipItem rows during generatePayslip()
 * - Writes JournalEntry rows via JournalService when markAsPaid() is called
 * - Calls loan.recordRepayment() for active employee loans on payment
 * - Tracks user events via UserEventService after generatePayslips() completes
 *
 * Idempotency:
 * - generatePayslips() is idempotent per period: employees with an existing payslip
 *   are skipped; a pessimistic lock prevents duplicate inserts from concurrent runs
 * - generatePayslip() returns the existing payslip if one already exists for the
 *   period+employee combination (lockForUpdate guards the check-then-insert)
 * - Loan EMI items are inserted only when no item with the same reference_id exists,
 *   making repeated calls to calculatePayslipItems() safe for the same payslip
 *
 * CONTRACT:
 * - generatePayslips() must be called inside a DB::transaction (it acquires its own
 *   inner transaction via lockForUpdate; nesting is safe with InnoDB savepoints)
 * - markAsPaid() must only be called for STATUS_APPROVED payslips
 * - erp.default_accounts.salary_expense and salary_payable must be configured before
 *   any payslip can be marked as paid
 * - closePeriod() requires all payslips to be in STATUS_PAID or STATUS_CANCELLED
 */
class PayrollService
{
    use ChecksIdempotency, StructuredLogger;
    public function __construct(
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private NumberGeneratorService $numberGenerator,
        private AttendanceService $attendanceService,
        private UserEventService $userEventService,
        private StatutoryDeductionService $statutoryDeductionService
    ) {}

    /**
     * Create a payroll period.
     */
    public function createPeriod(array $data): PayrollPeriod
    {
        return PayrollPeriod::create([
            'organization_id' => auth()->user()->organization_id,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'payment_date' => $data['payment_date'] ?? null,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * Generate payslips for a payroll period.
     *
     * Idempotent: a duplicate call for the same period within 24 h returns the
     * original payslip count without re-generating payslips.
     */
    public function generatePayslips(PayrollPeriod $period, int $userId): int
    {
        /** @var int $count */
        $count = $this->withFinancialIdempotency(
            key: "payroll_period:{$period->id}:generate",
            operation: 'payroll.generate',
            orgId: $period->organization_id,
            callback: fn (): int => $this->executeGeneratePayslips($period, $userId),
        );

        try {
            $this->userEventService->track(
                UserEvent::PAYROLL_PROCESSED,
                ['period_id' => $period->id, 'period_name' => $period->name, 'payslip_count' => $count],
                $userId,
                $period->organization_id,
            );
        } catch (\Throwable $e) {
            $this->logWarning('Event tracking failed', ['event' => UserEvent::PAYROLL_PROCESSED, 'error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * Core generate logic — wrapped by generatePayslips() idempotency guard.
     */
    private function executeGeneratePayslips(PayrollPeriod $period, int $userId): int
    {
        return DB::transaction(function () use ($period, $userId): int {
            $period = PayrollPeriod::where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$period->canBeProcessed()) {
                throw new \InvalidArgumentException('Payroll period cannot be processed.');
            }

            $period->transitionTo(PayrollPeriod::STATUS_PROCESSING);

            $count = 0;

            Employee::active()
                ->whereHas('currentSalary')
                ->where('organization_id', $period->organization_id)
                ->chunkById(50, function ($employees) use ($period, &$count): void {
                    foreach ($employees as $employee) {
                        if (Payslip::where('payroll_period_id', $period->id)
                            ->where('employee_id', $employee->id)
                            ->exists()) {
                            continue;
                        }

                        $this->generatePayslip($period, $employee);
                        $count++;
                    }
                });

            $period->transitionTo(PayrollPeriod::STATUS_PROCESSED, [
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            return $count;
        });
    }

    /**
     * Generate payslip for a single employee.
     */
    public function generatePayslip(PayrollPeriod $period, Employee $employee): Payslip
    {
        // Idempotency guard: return existing payslip if already generated for this period+employee.
        // lockForUpdate prevents a duplicate from being inserted by a concurrent request that
        // passed the exists() check in generatePayslips() just before we enter this transaction.
        $existing = Payslip::where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $salary = $employee->currentSalary;

        if (!$salary) {
            throw new \InvalidArgumentException('Employee has no active salary assignment.');
        }

        // Get attendance summary
        $attendanceSummary = $this->attendanceService->getEmployeeSummary(
            $employee,
            $period->start_date,
            $period->end_date
        );

        // Get approved leaves (unpaid)
        $unpaidLeaveDays = LeaveRequest::forEmployee($employee->id)
            ->approved()
            ->inDateRange($period->start_date, $period->end_date)
            ->whereHas('leaveType', fn($q) => $q->where('is_paid', false))
            ->sum('total_days');

        // Create payslip
        $payslip = Payslip::create([
            'organization_id' => $employee->organization_id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'employee_salary_id' => $salary->id,
            'payslip_number' => $this->numberGenerator->generate('SLIP'),
            'payment_date' => $period->payment_date,
            'total_working_days' => $attendanceSummary['total_days'] - $attendanceSummary['holiday'] - $attendanceSummary['weekend'],
            'days_worked' => $attendanceSummary['working_days'],
            'days_on_leave' => $attendanceSummary['on_leave'],
            'unpaid_leave_days' => $unpaidLeaveDays,
            'overtime_hours' => $attendanceSummary['total_overtime_hours'],
            'currency_code' => $salary->currency_code,
            'status' => Payslip::STATUS_DRAFT,
        ]);

        // Calculate earnings and deductions
        $this->calculatePayslipItems($payslip, $salary, $attendanceSummary, $unpaidLeaveDays);

        return $payslip->fresh(['items', 'employee', 'payrollPeriod']);
    }

    /**
     * Calculate payslip items (earnings and deductions).
     */
    protected function calculatePayslipItems(
        Payslip $payslip,
        $salary,
        array $attendanceSummary,
        float $unpaidLeaveDays
    ): void {
        $sortOrder = 0;
        $grossEarnings = 0;
        $totalDeductions = 0;
        $taxableIncome = 0;

        // Pro-rata factor (for unpaid leave)
        if (bccomp((string) $payslip->total_working_days, '0', 4) <= 0) {
            $proRataFactor = '1.0000';
        } elseif ($unpaidLeaveDays > 0) {
            $proRataFactor = bcdiv(
                bcsub((string) $payslip->total_working_days, (string) $unpaidLeaveDays, 4),
                (string) $payslip->total_working_days,
                4
            );
        } else {
            $proRataFactor = '1.0000';
        }

        // Process earnings
        foreach ($salary->getEarnings() as $component) {
            $amount = $component->amount;

            if (bccomp((string) $amount, '0', 4) < 0) {
                throw new \InvalidArgumentException("Salary component amount cannot be negative: {$component->salaryComponent->name}");
            }

            // Apply pro-rata if component supports it
            if ($component->salaryComponent->is_pro_rata) {
                $amount = bcmul((string)$amount, (string)$proRataFactor, 4);
            }

            if ($amount > 0) {
                PayslipItem::create([
                    'payslip_id' => $payslip->id,
                    'salary_component_id' => $component->salary_component_id,
                    'type' => 'earning',
                    'name' => $component->salaryComponent->name,
                    'amount' => $amount,
                    'ytd_amount' => $this->getYtdAmount($payslip->employee_id, $component->salary_component_id),
                    'sort_order' => $sortOrder++,
                ]);

                $grossEarnings = bcadd((string) $grossEarnings, (string) $amount, 4);

                if ($component->salaryComponent->is_taxable) {
                    $taxableIncome = bcadd((string) $taxableIncome, (string) $amount, 4);
                }
            }
        }

        // Process deductions
        foreach ($salary->getDeductions() as $component) {
            $amount = $component->amount;

            if (bccomp((string) $amount, '0', 4) < 0) {
                throw new \InvalidArgumentException("Salary component amount cannot be negative: {$component->salaryComponent->name}");
            }

            // Apply pro-rata if component supports it
            if ($component->salaryComponent->is_pro_rata) {
                $amount = bcmul((string)$amount, (string)$proRataFactor, 4);
            }

            if ($amount > 0) {
                PayslipItem::create([
                    'payslip_id' => $payslip->id,
                    'salary_component_id' => $component->salary_component_id,
                    'type' => 'deduction',
                    'name' => $component->salaryComponent->name,
                    'amount' => $amount,
                    'ytd_amount' => $this->getYtdAmount($payslip->employee_id, $component->salary_component_id),
                    'sort_order' => $sortOrder++,
                ]);

                $totalDeductions = bcadd((string) $totalDeductions, (string) $amount, 4);
            }
        }

        // Auto-calculate statutory deductions (GOSI, EPF, ESI, etc.) for the
        // employee's country. Skip codes already covered by salary structure
        // to avoid double-deduction.
        $existingCodes = $payslip->items()
            ->where('type', 'deduction')
            ->whereNotNull('salary_component_id')
            ->with('salaryComponent:id,code')
            ->get()
            ->pluck('salaryComponent.code')
            ->filter()
            ->map(fn ($c) => strtoupper($c))
            ->values()
            ->toArray();

        $statutory = $this->statutoryDeductionService->calculateDeductions(
            $payslip->employee,
            (float) $grossEarnings
        );

        foreach ($statutory['employee_deductions'] as $deduction) {
            if (in_array(strtoupper($deduction['code']), $existingCodes, true)) {
                continue; // already covered by salary structure
            }

            PayslipItem::create([
                'payslip_id'          => $payslip->id,
                'salary_component_id' => null,
                'type'                => 'deduction',
                'name'                => $deduction['name'],
                'amount'              => $deduction['amount'],
                'reference_type'      => 'statutory',
                'reference_id'        => null,
                'ytd_amount'          => 0,
                'sort_order'          => $sortOrder++,
            ]);

            $totalDeductions = bcadd((string) $totalDeductions, (string) $deduction['amount'], 4);
        }

        // Add loan EMI deductions — one PayslipItem per loan schedule entry to
        // prevent double-deduction if the payslip is regenerated.
        foreach ($this->getLoanDeductionItems($payslip) as $loanItem) {
            $exists = $payslip->items()
                ->where('reference_type', 'loan')
                ->where('reference_id', $loanItem['schedule_id'])
                ->exists();

            if (!$exists) {
                PayslipItem::create([
                    'payslip_id' => $payslip->id,
                    'salary_component_id' => null,
                    'type' => 'deduction',
                    'name' => $loanItem['name'],
                    'amount' => $loanItem['amount'],
                    'reference_type' => 'loan',
                    'reference_id' => $loanItem['schedule_id'],
                    'ytd_amount' => 0,
                    'sort_order' => $sortOrder++,
                ]);

                $totalDeductions = bcadd((string) $totalDeductions, (string) $loanItem['amount'], 4);
            }
        }

        // Update payslip totals
        $payslip->update([
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => max(0, bcsub((string) $grossEarnings, (string) $totalDeductions, 4)),
            'taxable_income' => $taxableIncome,
        ]);
    }

    /**
     * Return per-loan-schedule deduction items for idempotent PayslipItem creation.
     *
     * Each element: ['schedule_id' => int, 'name' => string, 'amount' => float]
     *
     * @return array<int, array{schedule_id: int, name: string, amount: float}>
     */
    protected function getLoanDeductionItems(Payslip $payslip): array
    {
        $loans = EmployeeLoan::where('employee_id', $payslip->employee_id)
            ->where('organization_id', $payslip->employee->organization_id ?? auth()->user()->organization_id)
            ->active()
            ->get();

        $items = [];

        foreach ($loans as $loan) {
            $nextRepayment = $loan->getNextRepayment();
            if ($nextRepayment && $nextRepayment->due_date->lte($payslip->payrollPeriod->end_date)) {
                $items[] = [
                    'schedule_id' => $nextRepayment->id,
                    'name' => 'Loan Repayment',
                    'amount' => (float) $nextRepayment->total_amount,
                ];
            }
        }

        return $items;
    }

    /**
     * Get year-to-date amount for a component.
     * Uses the organization's open fiscal year start date; falls back to
     * the calendar year start when no open fiscal year is found.
     */
    protected function getYtdAmount(int $employeeId, int $componentId): float
    {
        $orgId = auth()->user()?->organization_id;

        $fiscalYear = $orgId
            ? \App\Models\Accounting\FiscalYear::where('organization_id', $orgId)
                ->where('status', 'open')
                ->first()
            : null;

        $ytdStart = $fiscalYear
            ? \Illuminate\Support\Carbon::parse($fiscalYear->start_date)
            : \Illuminate\Support\Carbon::now()->startOfYear();

        return (float) PayslipItem::whereHas('payslip', function ($q) use ($employeeId, $orgId, $ytdStart) {
            $q->where('employee_id', $employeeId)
                ->where('created_at', '>=', $ytdStart)
                ->whereIn('status', [Payslip::STATUS_APPROVED, Payslip::STATUS_PAID])
                ->when($orgId, fn($q2) => $q2->where('organization_id', $orgId));
        })
            ->where('salary_component_id', $componentId)
            ->sum('amount');
    }

    /**
     * Submit a payslip for approval (DRAFT → PENDING).
     */
    public function submitPayslip(Payslip $payslip, int $userId): Payslip
    {
        if ($payslip->status !== Payslip::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft payslips can be submitted for approval.');
        }

        $payslip->update(['status' => Payslip::STATUS_PENDING]);

        return $payslip->fresh();
    }

    /**
     * Approve a payslip.
     */
    public function approvePayslip(Payslip $payslip, int $userId): Payslip
    {
        if ($payslip->status !== Payslip::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending payslips can be approved.');
        }

        $payslip->update([
            'status' => Payslip::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $payslip->fresh();
    }

    /**
     * Mark payslip as paid.
     */
    public function markAsPaid(Payslip $payslip, string $paymentMode, ?string $paymentReference = null): Payslip
    {
        if ($payslip->status !== Payslip::STATUS_APPROVED) {
            throw new \InvalidArgumentException('Only approved payslips can be marked as paid.');
        }

        return DB::transaction(function () use ($payslip, $paymentMode, $paymentReference) {
            // Create journal entry
            $journal = $this->createJournalEntry($payslip);

            // Process loan repayments
            $this->processLoanRepayments($payslip);

            $payslip->update([
                'status' => Payslip::STATUS_PAID,
                'payment_mode' => $paymentMode,
                'payment_reference' => $paymentReference,
                'paid_at' => now(),
                'journal_entry_id' => $journal->id,
            ]);

            return $payslip->fresh();
        });
    }

    /**
     * Create journal entry for payslip.
     */
    protected function createJournalEntry(Payslip $payslip): \App\Models\Accounting\JournalEntry
    {
        return $this->journalEntryFactory->forPayslip($payslip);
    }

    /**
     * Process loan repayments from payslip.
     */
    protected function processLoanRepayments(Payslip $payslip): void
    {
        $loans = EmployeeLoan::where('employee_id', $payslip->employee_id)
            ->active()
            ->get();

        foreach ($loans as $loan) {
            $nextRepayment = $loan->getNextRepayment();
            if ($nextRepayment && $nextRepayment->due_date->lte($payslip->payrollPeriod->end_date)) {
                $loan->recordRepayment($nextRepayment->total_amount, $payslip->id);
            }
        }
    }

    /**
     * Close a payroll period.
     */
    public function closePeriod(PayrollPeriod $period, int $userId): PayrollPeriod
    {
        if (!$period->canBeClosed()) {
            throw new \InvalidArgumentException('Payroll period cannot be closed.');
        }

        // Check if all payslips are paid
        $unpaid = $period->payslips()
            ->whereNotIn('status', [Payslip::STATUS_PAID, Payslip::STATUS_CANCELLED])
            ->count();

        if ($unpaid > 0) {
            throw new \InvalidArgumentException("Cannot close period. {$unpaid} payslips are not yet paid.");
        }

        $period->update([
            'status' => PayrollPeriod::STATUS_CLOSED,
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);

        return $period->fresh();
    }

    /**
     * Get payroll summary for a period.
     *
     * Uses DB aggregates instead of loading the full payslip collection into
     * PHP memory and calling ->sum() / ->where()->count() on a Collection.
     */
    public function getPeriodSummary(PayrollPeriod $period): array
    {
        $aggregates = DB::table('payslips')
            ->where('payroll_period_id', $period->id)
            ->where('organization_id', $period->organization_id)
            ->selectRaw('
                COUNT(*) as total_payslips,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid_count,
                COALESCE(SUM(gross_earnings), 0) as total_gross,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_salary), 0) as total_net
            ', [
                Payslip::STATUS_DRAFT,
                Payslip::STATUS_PENDING,
                Payslip::STATUS_APPROVED,
                Payslip::STATUS_PAID,
            ])
            ->first();

        return [
            'total_employees' => (int) ($aggregates->total_payslips ?? 0),
            'total_gross'     => (float) ($aggregates->total_gross ?? 0),
            'total_deductions' => (float) ($aggregates->total_deductions ?? 0),
            'total_net'       => (float) ($aggregates->total_net ?? 0),
            'draft_count'     => (int) ($aggregates->draft_count ?? 0),
            'pending_count'   => (int) ($aggregates->pending_count ?? 0),
            'approved_count'  => (int) ($aggregates->approved_count ?? 0),
            'paid_count'      => (int) ($aggregates->paid_count ?? 0),
        ];
    }
}
