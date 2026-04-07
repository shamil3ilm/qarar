<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\CompensationReview;
use App\Models\HR\CompensationReviewItem;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompensationService
{
    /**
     * Paginate compensation reviews with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        return CompensationReview::with(['approver'])
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when(
                $filters['review_date_from'] ?? null,
                fn($q, $v) => $q->where('review_date', '>=', $v)
            )
            ->when(
                $filters['review_date_to'] ?? null,
                fn($q, $v) => $q->where('review_date', '<=', $v)
            )
            ->orderBy('review_date', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a new compensation review.
     */
    public function createReview(array $data): CompensationReview
    {
        return CompensationReview::create([
            'organization_id' => $data['organization_id'] ?? auth()->user()?->organization_id,
            'review_name'     => $data['review_name'],
            'review_date'     => $data['review_date'],
            'effective_date'  => $data['effective_date'],
            'budget_amount'   => $data['budget_amount'] ?? 0,
            'status'          => CompensationReview::STATUS_DRAFT,
        ]);
    }

    /**
     * Add an employee item to a compensation review.
     */
    public function addItem(CompensationReview $review, array $data): CompensationReviewItem
    {
        if (!$review->isEditable()) {
            throw new \InvalidArgumentException(
                "Cannot add items to a review with status '{$review->status}'."
            );
        }

        $employee = Employee::findOrFail($data['employee_id']);

        $currentSalary = $data['current_salary']
            ?? (float) ($employee->currentSalary?->basic_salary ?? 0);

        $item = new CompensationReviewItem([
            'review_id'       => $review->id,
            'employee_id'     => $employee->id,
            'current_salary'  => $currentSalary,
            'proposed_salary' => $data['proposed_salary'] ?? null,
            'adjustment_type' => $data['adjustment_type'] ?? CompensationReviewItem::ADJUSTMENT_MERIT,
            'justification'   => $data['justification'] ?? null,
            'status'          => CompensationReviewItem::STATUS_PENDING,
        ]);

        if ($item->proposed_salary !== null) {
            $item->recalculateIncrease();
        }

        $item->save();

        // Update allocated_amount on review
        $this->recalculateAllocated($review);

        return $item;
    }

    /**
     * Bulk recommend increases for all pending items based on a percentage.
     */
    public function bulkRecommend(CompensationReview $review, array $params): int
    {
        if (!$review->isEditable()) {
            throw new \InvalidArgumentException(
                "Review is not in an editable state."
            );
        }

        $increasePercent = (float) ($params['increase_percentage'] ?? 0);

        if ($increasePercent <= 0) {
            throw new \InvalidArgumentException('increase_percentage must be greater than 0.');
        }

        $updated = 0;

        DB::transaction(function () use ($review, $increasePercent, &$updated): void {
            $items = $review->items()->pending()->get();

            foreach ($items as $item) {
                $currentSalary   = (string) $item->current_salary;
                $multiplier      = bcadd('1', bcdiv((string) $increasePercent, '100', 4), 4);
                $proposed        = bcmul($currentSalary, $multiplier, 4);
                $item->proposed_salary     = $proposed;
                $item->increase_amount     = bcsub($proposed, $currentSalary, 4);
                $item->increase_percentage = $increasePercent;
                $item->status              = CompensationReviewItem::STATUS_RECOMMENDED;
                $item->save();
                $updated++;
            }

            $this->recalculateAllocated($review);
            $review->status = CompensationReview::STATUS_IN_PROGRESS;
            $review->save();
        });

        return $updated;
    }

    /**
     * Approve a compensation review.
     */
    public function approve(CompensationReview $review): CompensationReview
    {
        if ($review->status !== CompensationReview::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException(
                "Only in-progress reviews can be approved. Current status: '{$review->status}'."
            );
        }

        return DB::transaction(function () use ($review): CompensationReview {
            // Approve all recommended items
            $review->items()
                ->where('status', CompensationReviewItem::STATUS_RECOMMENDED)
                ->update(['status' => CompensationReviewItem::STATUS_APPROVED]);

            $review->status      = CompensationReview::STATUS_APPROVED;
            $review->approved_by = auth()->id();
            $review->save();

            return $review->fresh();
        });
    }

    /**
     * Apply approved review — update employee salary records.
     */
    public function apply(CompensationReview $review): int
    {
        if ($review->status !== CompensationReview::STATUS_APPROVED) {
            throw new \InvalidArgumentException(
                "Only approved reviews can be applied. Current status: '{$review->status}'."
            );
        }

        $applied = 0;

        DB::transaction(function () use ($review, &$applied): void {
            $items = $review->items()
                ->where('status', CompensationReviewItem::STATUS_APPROVED)
                ->whereNotNull('proposed_salary')
                ->with('employee.currentSalary')
                ->get();

            foreach ($items as $item) {
                $employee = $item->employee;

                if ($employee === null) {
                    Log::warning("CompensationService: employee #{$item->employee_id} not found during apply.");
                    continue;
                }

                // Deactivate current salary record
                $employee->salaryHistory()
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'effective_to' => $review->effective_date]);

                // Create new salary record
                EmployeeSalary::create([
                    'employee_id'    => $employee->id,
                    'organization_id' => $employee->organization_id,
                    'basic_salary'   => $item->proposed_salary,
                    'effective_from' => $review->effective_date,
                    'is_current'     => true,
                    'notes'          => "Compensation review: {$review->review_name}",
                ]);

                $item->status = CompensationReviewItem::STATUS_APPLIED ?? 'applied';
                $item->save();

                $applied++;
            }

            $review->status = CompensationReview::STATUS_APPLIED;
            $review->save();
        });

        return $applied;
    }

    /**
     * Recalculate allocated_amount on a review based on increase_amounts of recommended/approved items.
     */
    private function recalculateAllocated(CompensationReview $review): void
    {
        $total = $review->items()
            ->whereIn('status', [
                CompensationReviewItem::STATUS_RECOMMENDED,
                CompensationReviewItem::STATUS_APPROVED,
            ])
            ->sum('increase_amount');

        $review->allocated_amount = (float) $total;
        $review->saveQuietly();
    }
}
