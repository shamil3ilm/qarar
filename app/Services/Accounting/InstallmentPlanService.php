<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\InstallmentPlan;
use App\Models\Accounting\InstallmentSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Installment Payment Plan Service (SAP FI-AR F-36 / FI-AP F-59 equivalent).
 *
 * Responsibilities:
 *  - Create a plan with auto-generated equal or custom installment schedule
 *  - Activate / cancel plans
 *  - Record payments against individual installments
 *  - Mark overdue installments
 *  - Report: upcoming due dates, overdue amounts
 */
class InstallmentPlanService
{
    // =========================================================================
    // Plan management
    // =========================================================================

    public function listPlans(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = InstallmentPlan::where('organization_id', $organizationId)
            ->with(['schedules', 'contact:id,company_name,contact_name'])
            ->orderByDesc('start_date');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Create an installment plan with an automatically-generated equal schedule.
     *
     * @param  array  $data  {document_type, document_id, contact_id?, total_amount, currency_code, start_date,
     *                        installment_count, frequency_days, [schedules: [{amount, due_date}]]}
     */
    public function create(array $data, int $organizationId, int $userId): InstallmentPlan
    {
        $totalAmount      = (float) $data['total_amount'];
        $installmentCount = (int) $data['installment_count'];

        if ($installmentCount < 2) {
            throw new InvalidArgumentException('Installment plan must have at least 2 installments.');
        }
        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Total amount must be positive.');
        }

        return DB::transaction(function () use ($data, $totalAmount, $installmentCount, $organizationId, $userId): InstallmentPlan {
            $plan = InstallmentPlan::create([
                'organization_id'   => $organizationId,
                'document_type'     => $data['document_type'],
                'document_id'       => $data['document_id'],
                'contact_id'        => $data['contact_id'] ?? null,
                'currency_code'     => $data['currency_code'] ?? 'SAR',
                'total_amount'      => $totalAmount,
                'total_paid'        => 0,
                'outstanding'       => $totalAmount,
                'installment_count' => $installmentCount,
                'status'            => InstallmentPlan::STATUS_DRAFT,
                'start_date'        => $data['start_date'],
                'end_date'          => null,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $userId,
            ]);

            // Build schedule
            $schedules = $data['schedules'] ?? null;
            if (!empty($schedules)) {
                $this->createCustomSchedule($plan, $schedules, $totalAmount);
            } else {
                $this->createEqualSchedule($plan, $totalAmount, $installmentCount, $data['start_date'], (int) ($data['frequency_days'] ?? 30));
            }

            return $plan->load('schedules');
        });
    }

    public function activate(InstallmentPlan $plan): InstallmentPlan
    {
        if ($plan->status !== InstallmentPlan::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft plans can be activated.');
        }

        $plan->update(['status' => InstallmentPlan::STATUS_ACTIVE]);

        return $plan->fresh('schedules');
    }

    public function cancel(InstallmentPlan $plan, string $reason = ''): InstallmentPlan
    {
        if ($plan->status === InstallmentPlan::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Cannot cancel a completed plan.');
        }

        $plan->update([
            'status' => InstallmentPlan::STATUS_CANCELLED,
            'notes'  => $plan->notes ? $plan->notes . ' | Cancelled: ' . $reason : 'Cancelled: ' . $reason,
        ]);

        // Cancel all unpaid schedules
        $plan->schedules()
            ->whereNotIn('status', [InstallmentSchedule::STATUS_PAID, InstallmentSchedule::STATUS_WAIVED])
            ->update(['status' => InstallmentSchedule::STATUS_WAIVED]);

        return $plan->fresh('schedules');
    }

    // =========================================================================
    // Record payment against an installment
    // =========================================================================

    /**
     * Apply a payment to a specific installment schedule line.
     *
     * @param  array  $paymentData  {payment_id, payment_type, paid_amount, paid_date}
     */
    public function recordPayment(
        InstallmentSchedule $schedule,
        array $paymentData
    ): InstallmentSchedule {
        $plan = $schedule->plan;

        if ($plan->status !== InstallmentPlan::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Can only record payments against active plans.');
        }
        if ($schedule->isPaid()) {
            throw new InvalidArgumentException('This installment has already been fully paid.');
        }

        return DB::transaction(function () use ($schedule, $paymentData, $plan): InstallmentSchedule {
            $paidAmount = (float) $paymentData['paid_amount'];
            $newPaid    = round((float) $schedule->paid_amount + $paidAmount, 4);
            $remaining  = round((float) $schedule->amount - $newPaid, 4);

            $newStatus = match (true) {
                $remaining <= 0.0005 => InstallmentSchedule::STATUS_PAID,
                $newPaid > 0         => InstallmentSchedule::STATUS_PARTIAL,
                default              => $schedule->status,
            };

            $schedule->update([
                'paid_amount'  => $newPaid,
                'paid_date'    => $remaining <= 0.0005 ? $paymentData['paid_date'] : null,
                'status'       => $newStatus,
                'payment_id'   => $paymentData['payment_id'] ?? null,
                'payment_type' => $paymentData['payment_type'] ?? null,
            ]);

            // Update plan totals
            $planTotalPaid = round((float) $plan->total_paid + $paidAmount, 4);
            $planOutstanding = round((float) $plan->total_amount - $planTotalPaid, 4);

            $allPaid = $plan->schedules()->where('status', '!=', InstallmentSchedule::STATUS_PAID)->doesntExist();
            $planStatus = $allPaid ? InstallmentPlan::STATUS_COMPLETED : $plan->status;

            $plan->update([
                'total_paid'  => $planTotalPaid,
                'outstanding' => max(0, $planOutstanding),
                'status'      => $planStatus,
                'end_date'    => $allPaid ? $paymentData['paid_date'] : $plan->end_date,
            ]);

            return $schedule->fresh();
        });
    }

    // =========================================================================
    // Overdue marking
    // =========================================================================

    /**
     * Mark all pending installments past their due date as overdue.
     * Intended to be called from a daily scheduled job.
     */
    public function markOverdue(int $organizationId): int
    {
        return InstallmentSchedule::withoutGlobalScopes()
            ->whereHas('plan', fn ($q) => $q->where('organization_id', $organizationId)->where('status', InstallmentPlan::STATUS_ACTIVE))
            ->where('status', InstallmentSchedule::STATUS_PENDING)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => InstallmentSchedule::STATUS_OVERDUE]);
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Upcoming installments due within the next N days.
     */
    public function upcomingDue(int $organizationId, int $days = 30): Collection
    {
        return InstallmentSchedule::withoutGlobalScopes()
            ->whereHas('plan', fn ($q) => $q->where('organization_id', $organizationId)->where('status', InstallmentPlan::STATUS_ACTIVE))
            ->whereIn('status', [InstallmentSchedule::STATUS_PENDING, InstallmentSchedule::STATUS_PARTIAL])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->with(['plan:id,uuid,document_type,document_id,currency_code,contact_id'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Overdue summary by contact for an organisation.
     */
    public function overdueSummary(int $organizationId): Collection
    {
        return InstallmentSchedule::withoutGlobalScopes()
            ->whereHas('plan', fn ($q) => $q->where('organization_id', $organizationId)->where('status', InstallmentPlan::STATUS_ACTIVE))
            ->where('status', InstallmentSchedule::STATUS_OVERDUE)
            ->with(['plan:id,uuid,contact_id,currency_code'])
            ->selectRaw('installment_plan_id, SUM(amount - paid_amount) as overdue_amount, COUNT(*) as overdue_count')
            ->groupBy('installment_plan_id')
            ->get();
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function createEqualSchedule(
        InstallmentPlan $plan,
        float $totalAmount,
        int $count,
        string $startDate,
        int $frequencyDays
    ): void {
        $baseAmount     = round($totalAmount / $count, 4);
        $lastAmount     = round($totalAmount - ($baseAmount * ($count - 1)), 4); // absorb rounding
        $dueDate        = \Carbon\Carbon::parse($startDate);

        for ($i = 1; $i <= $count; $i++) {
            $amount = ($i === $count) ? $lastAmount : $baseAmount;

            InstallmentSchedule::create([
                'installment_plan_id' => $plan->id,
                'installment_number'  => $i,
                'amount'              => $amount,
                'paid_amount'         => 0,
                'due_date'            => $dueDate->toDateString(),
                'status'              => InstallmentSchedule::STATUS_PENDING,
            ]);

            $dueDate->addDays($frequencyDays);
        }
    }

    private function createCustomSchedule(InstallmentPlan $plan, array $schedules, float $totalAmount): void
    {
        $scheduledTotal = array_sum(array_column($schedules, 'amount'));
        if (abs($scheduledTotal - $totalAmount) > 0.01) {
            throw new InvalidArgumentException(
                sprintf('Custom schedule amounts (%.4f) must sum to the plan total (%.4f).', $scheduledTotal, $totalAmount)
            );
        }

        foreach ($schedules as $i => $item) {
            InstallmentSchedule::create([
                'installment_plan_id' => $plan->id,
                'installment_number'  => $i + 1,
                'amount'              => (float) $item['amount'],
                'paid_amount'         => 0,
                'due_date'            => $item['due_date'],
                'status'              => InstallmentSchedule::STATUS_PENDING,
                'notes'               => $item['notes'] ?? null,
            ]);
        }
    }
}
