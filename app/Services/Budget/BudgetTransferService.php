<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Models\Budget\Budget;
use App\Models\Budget\BudgetLine;
use App\Models\Budget\BudgetTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Budget Transfer Service — SAP FM budget transfer equivalent (T-code FM2S).
 *
 * Moves budget appropriation from one budget line to another with full
 * availability control and an approval workflow:
 *   draft → submitted → approved → posted
 *
 * On posting the source line's total_amount is decreased and the
 * target line's total_amount is increased by the transfer amount.
 */
class BudgetTransferService
{
    /**
     * Create a draft transfer request.
     *
     * @param  array{
     *     from_budget_line_id: int,
     *     to_budget_line_id: int,
     *     amount: float|string,
     *     reason: string,
     *     notes?: string,
     * } $data
     */
    public function create(array $data, User $requestedBy): BudgetTransfer
    {
        $fromLine = BudgetLine::findOrFail($data['from_budget_line_id']);
        $toLine   = BudgetLine::findOrFail($data['to_budget_line_id']);

        $this->assertSameOrganization($fromLine, $toLine, $requestedBy);
        $this->assertPositiveAmount($data['amount']);
        $this->assertSufficientAvailableBudget($fromLine, (float) $data['amount']);

        return DB::transaction(function () use ($data, $fromLine, $toLine, $requestedBy): BudgetTransfer {
            return BudgetTransfer::create([
                'organization_id'     => $requestedBy->organization_id,
                'transfer_number'     => $this->generateTransferNumber($requestedBy->organization_id),
                'from_budget_id'      => $fromLine->budget_id,
                'from_budget_line_id' => $fromLine->id,
                'to_budget_id'        => $toLine->budget_id,
                'to_budget_line_id'   => $toLine->id,
                'amount'              => $data['amount'],
                'reason'              => $data['reason'],
                'notes'               => $data['notes'] ?? null,
                'status'              => BudgetTransfer::STATUS_DRAFT,
                'requested_by'        => $requestedBy->id,
            ]);
        });
    }

    /** Advance draft → submitted for approval. */
    public function submit(BudgetTransfer $transfer): BudgetTransfer
    {
        $this->assertTransition($transfer, BudgetTransfer::STATUS_SUBMITTED);

        $transfer->update(['status' => BudgetTransfer::STATUS_SUBMITTED]);

        return $transfer->fresh();
    }

    /** Approve and immediately post the transfer. */
    public function approve(BudgetTransfer $transfer, User $approver): BudgetTransfer
    {
        $this->assertTransition($transfer, BudgetTransfer::STATUS_APPROVED);

        return DB::transaction(function () use ($transfer, $approver): BudgetTransfer {
            $transfer->update([
                'status'      => BudgetTransfer::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $this->post($transfer, $approver);
        });
    }

    /** Reject a submitted transfer. */
    public function reject(BudgetTransfer $transfer, User $rejector, string $reason): BudgetTransfer
    {
        $this->assertTransition($transfer, BudgetTransfer::STATUS_REJECTED);

        $transfer->update([
            'status'           => BudgetTransfer::STATUS_REJECTED,
            'approved_by'      => $rejector->id,
            'rejection_reason' => $reason,
        ]);

        return $transfer->fresh();
    }

    /**
     * Post an approved transfer: debit source line, credit target line.
     *
     * Re-validates availability at post time to guard against concurrent
     * transfers consuming the same budget.
     */
    public function post(BudgetTransfer $transfer, User $postedBy): BudgetTransfer
    {
        $this->assertTransition($transfer, BudgetTransfer::STATUS_POSTED);

        return DB::transaction(function () use ($transfer, $postedBy): BudgetTransfer {
            $fromLine = BudgetLine::lockForUpdate()->findOrFail($transfer->from_budget_line_id);
            $toLine   = BudgetLine::lockForUpdate()->findOrFail($transfer->to_budget_line_id);

            $this->assertSufficientAvailableBudget($fromLine, (float) $transfer->amount);

            // Debit source
            $fromLine->decrement('total_amount', $transfer->amount);

            // Credit target
            $toLine->increment('total_amount', $transfer->amount);

            $transfer->update([
                'status'    => BudgetTransfer::STATUS_POSTED,
                'posted_by' => $postedBy->id,
                'posted_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Queries
    // ----------------------------------------------------------------

    public function getForOrganization(int $organizationId, array $filters = [])
    {
        $query = BudgetTransfer::where('organization_id', $organizationId)
            ->with(['fromBudget', 'fromBudgetLine', 'toBudget', 'toBudgetLine', 'requester', 'approver']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_budget_id'])) {
            $query->where('from_budget_id', $filters['from_budget_id']);
        }

        if (isset($filters['to_budget_id'])) {
            $query->where('to_budget_id', $filters['to_budget_id']);
        }

        return $query->latest()->paginate(25);
    }

    // ----------------------------------------------------------------
    // Invariants
    // ----------------------------------------------------------------

    private function assertTransition(BudgetTransfer $transfer, string $to): void
    {
        if (! $transfer->canTransition($to)) {
            throw new \LogicException("Cannot transition budget transfer from '{$transfer->status}' to '{$to}'.");
        }
    }

    private function assertPositiveAmount(float|string $amount): void
    {
        if ((float) $amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }
    }

    private function assertSufficientAvailableBudget(BudgetLine $line, float $amount): void
    {
        if ($line->getAvailableAmount() < $amount) {
            throw new \LogicException(
                sprintf(
                    'Insufficient available budget on line #%d. Available: %.2f, Requested: %.2f.',
                    $line->id,
                    $line->getAvailableAmount(),
                    $amount,
                )
            );
        }
    }

    private function assertSameOrganization(BudgetLine $from, BudgetLine $to, User $user): void
    {
        $fromOrgId = $from->budget->organization_id ?? null;
        $toOrgId   = $to->budget->organization_id ?? null;

        if ($fromOrgId !== $toOrgId || $fromOrgId !== $user->organization_id) {
            throw new \InvalidArgumentException('Budget lines must belong to the same organisation as the requesting user.');
        }
    }

    private function generateTransferNumber(int $organizationId): string
    {
        $count = BudgetTransfer::where('organization_id', $organizationId)->withTrashed()->count() + 1;

        return 'BT-' . date('Y') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
