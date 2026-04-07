<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseReport;
use App\Models\Expense\ExpenseReportItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseReportService
{
    /**
     * Create a new expense report.
     */
    public function create(array $data): ExpenseReport
    {
        return DB::transaction(function () use ($data) {
            $report = ExpenseReport::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ]);

            // Add initial expenses if provided
            if (!empty($data['expense_ids'])) {
                $this->addExpenses($report, $data['expense_ids']);
            }

            return $report->fresh(['reportItems.expense']);
        });
    }

    /**
     * Add expenses to a report.
     */
    public function addExpenses(ExpenseReport $report, array $expenseIds): ExpenseReport
    {
        if ($report->status !== ExpenseReport::STATUS_DRAFT) {
            throw new InvalidArgumentException('Can only add expenses to draft reports.');
        }

        return DB::transaction(function () use ($report, $expenseIds) {
            foreach ($expenseIds as $expenseId) {
                $expense = Expense::findOrFail($expenseId);

                // Verify expense belongs to same organization
                if ($expense->organization_id !== $report->organization_id) {
                    throw new InvalidArgumentException("Expense #{$expenseId} does not belong to this organization.");
                }

                // Check if expense is already in a report
                $existsInReport = ExpenseReportItem::where('expense_id', $expenseId)->exists();
                if ($existsInReport) {
                    throw new InvalidArgumentException("Expense #{$expenseId} is already in another report.");
                }

                // Verify expense is in valid status
                if (!in_array($expense->status, [Expense::STATUS_APPROVED, Expense::STATUS_DRAFT, Expense::STATUS_SUBMITTED])) {
                    throw new InvalidArgumentException("Expense #{$expenseId} has invalid status for reporting.");
                }

                $report->reportItems()->create([
                    'expense_id' => $expenseId,
                    'approved_amount' => null,
                ]);
            }

            $report->recalculateTotals();

            return $report->fresh(['reportItems.expense']);
        });
    }

    /**
     * Submit a report for approval.
     */
    public function submit(ExpenseReport $report): ExpenseReport
    {
        if ($report->status !== ExpenseReport::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft reports can be submitted.');
        }

        if ($report->reportItems()->count() === 0) {
            throw new InvalidArgumentException('Cannot submit an empty report.');
        }

        return DB::transaction(function () use ($report) {
            $report->update(['status' => ExpenseReport::STATUS_SUBMITTED]);
            return $report->fresh();
        });
    }

    /**
     * Approve a submitted report.
     */
    public function approve(ExpenseReport $report, int $approverId, ?array $itemApprovals = null): ExpenseReport
    {
        if ($report->status !== ExpenseReport::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted reports can be approved.');
        }

        return DB::transaction(function () use ($report, $approverId, $itemApprovals) {
            // Apply individual item approvals if provided
            if ($itemApprovals) {
                foreach ($itemApprovals as $approval) {
                    ExpenseReportItem::where('report_id', $report->id)
                        ->where('expense_id', $approval['expense_id'])
                        ->update([
                            'approved_amount' => $approval['approved_amount'],
                            'notes' => $approval['notes'] ?? null,
                        ]);
                }
            } else {
                // Auto-approve all at expense total amounts
                $report->reportItems()->each(function ($item) {
                    $item->update([
                        'approved_amount' => $item->expense->total_amount,
                    ]);
                });
            }

            $approvedAmount = $report->reportItems()->sum('approved_amount');

            $report->update([
                'status' => ExpenseReport::STATUS_APPROVED,
                'approved_amount' => $approvedAmount,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $report->fresh(['reportItems.expense', 'approvedBy']);
        });
    }

    /**
     * Reject a submitted report.
     */
    public function reject(ExpenseReport $report, string $reason): ExpenseReport
    {
        if ($report->status !== ExpenseReport::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted reports can be rejected.');
        }

        return DB::transaction(function () use ($report, $reason) {
            $report->update([
                'status' => ExpenseReport::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            return $report->fresh();
        });
    }

    /**
     * Mark a report as reimbursed/paid.
     */
    public function reimburse(ExpenseReport $report, array $data = []): ExpenseReport
    {
        if ($report->status !== ExpenseReport::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved reports can be reimbursed.');
        }

        return DB::transaction(function () use ($report, $data) {
            $reimbursedAmount = $data['reimbursed_amount'] ?? $report->approved_amount;

            $report->update([
                'status' => ExpenseReport::STATUS_PAID,
                'reimbursed_amount' => $reimbursedAmount,
                'paid_at' => now(),
            ]);

            // Mark associated expenses as paid
            $report->reportItems()->each(function ($item) {
                $item->expense->update([
                    'status' => Expense::STATUS_PAID,
                    'paid_at' => now(),
                ]);
            });

            return $report->fresh(['reportItems.expense']);
        });
    }
}
