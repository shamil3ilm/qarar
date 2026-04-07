<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\PayrollCorrection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollCorrectionService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = PayrollCorrection::query()
            ->with(['employee', 'originalPeriod', 'correctionPeriod', 'approver'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['employee_id']), fn($q) => $q->forEmployee((int) $filters['employee_id']))
            ->when(isset($filters['original_period_id']), fn($q) => $q->forPeriod((int) $filters['original_period_id']))
            ->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): PayrollCorrection
    {
        $originalAmount   = (float) $data['original_amount'];
        $correctedAmount  = (float) $data['corrected_amount'];
        $differenceAmount = $correctedAmount - $originalAmount;

        return PayrollCorrection::create([
            'employee_id'                  => $data['employee_id'],
            'original_payroll_period_id'   => $data['original_payroll_period_id'],
            'correction_payroll_period_id' => $data['correction_payroll_period_id'] ?? null,
            'correction_type'              => $data['correction_type'] ?? PayrollCorrection::TYPE_SALARY_CHANGE,
            'status'                       => PayrollCorrection::STATUS_DRAFT,
            'original_amount'              => $originalAmount,
            'corrected_amount'             => $correctedAmount,
            'difference_amount'            => $differenceAmount,
            'reason'                       => $data['reason'] ?? null,
        ]);
    }

    public function approve(PayrollCorrection $correction, int $approvedBy): PayrollCorrection
    {
        if (! $correction->canApprove()) {
            throw new InvalidArgumentException('Only draft corrections can be approved.');
        }

        $correction->update([
            'status'      => PayrollCorrection::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $correction->fresh();
    }

    public function post(PayrollCorrection $correction): PayrollCorrection
    {
        if (! $correction->canPost()) {
            throw new InvalidArgumentException('Only approved corrections can be posted.');
        }

        return DB::transaction(function () use ($correction): PayrollCorrection {
            $correction->update([
                'status'    => PayrollCorrection::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $correction->fresh();
        });
    }

    public function cancel(PayrollCorrection $correction): PayrollCorrection
    {
        if (! $correction->canCancel()) {
            throw new InvalidArgumentException('This correction cannot be cancelled.');
        }

        $correction->update(['status' => PayrollCorrection::STATUS_CANCELLED]);

        return $correction->fresh();
    }
}
