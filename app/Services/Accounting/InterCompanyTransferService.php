<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\InterCompanyTransfer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InterCompanyTransferService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Create a new inter-company transfer.
     */
    public function create(array $data, int $userId): InterCompanyTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $transfer = InterCompanyTransfer::create([
                'organization_id' => $data['organization_id'],
                'transfer_type' => $data['transfer_type'],
                'from_branch_id' => $data['from_branch_id'] ?? null,
                'from_bank_account_id' => $data['from_bank_account_id'] ?? null,
                'to_branch_id' => $data['to_branch_id'] ?? null,
                'to_bank_account_id' => $data['to_bank_account_id'] ?? null,
                'to_organization_id' => $data['to_organization_id'] ?? null,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'transfer_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'loan_id' => $data['loan_id'] ?? null,
                'created_by' => $data['created_by'] ?? $userId,
            ]);

            return $transfer->fresh([
                'fromBranch',
                'toBranch',
                'fromBankAccount',
                'toBankAccount',
                'createdBy',
            ]);
        });
    }

    /**
     * Approve a pending inter-company transfer.
     */
    public function approve(InterCompanyTransfer $transfer, int $userId): InterCompanyTransfer
    {
        if ($transfer->status !== InterCompanyTransfer::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending transfers can be approved.');
        }

        return DB::transaction(function () use ($transfer, $userId) {
            $transfer->transitionTo(InterCompanyTransfer::STATUS_APPROVED, [
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $transfer->fresh(['approvedBy']);
        });
    }

    /**
     * Complete an approved inter-company transfer with journal entries.
     */
    public function complete(InterCompanyTransfer $transfer): InterCompanyTransfer
    {
        if ($transfer->status !== InterCompanyTransfer::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved transfers can be completed.');
        }

        return DB::transaction(function () use ($transfer) {
            // Create journal entries if bank accounts have GL accounts
            $journalEntryId = null;

            if ($transfer->fromBankAccount?->gl_account_id && $transfer->toBankAccount?->gl_account_id) {
                $journalEntry = $this->journalService->createSimpleEntry(
                    organizationId: $transfer->organization_id,
                    branchId: $transfer->from_branch_id ?? $transfer->to_branch_id ?? 0,
                    debitAccountId: $transfer->toBankAccount->gl_account_id,
                    creditAccountId: $transfer->fromBankAccount->gl_account_id,
                    amount: (float) $transfer->amount,
                    description: "Inter-company transfer: {$transfer->transfer_number}",
                    reference: $transfer->transfer_number,
                    date: $transfer->transfer_date->toDateString()
                );

                $this->journalService->postEntry($journalEntry);
                $journalEntryId = $journalEntry->id;
            }

            $transfer->transitionTo(InterCompanyTransfer::STATUS_COMPLETED, [
                'journal_entry_id' => $journalEntryId,
            ]);

            return $transfer->fresh([
                'fromBranch',
                'toBranch',
                'fromBankAccount',
                'toBankAccount',
                'journalEntry',
            ]);
        });
    }

    /**
     * Reject/cancel a pending or approved transfer.
     */
    public function reject(InterCompanyTransfer $transfer, ?string $reason = null): InterCompanyTransfer
    {
        if (!in_array($transfer->status, [InterCompanyTransfer::STATUS_PENDING, InterCompanyTransfer::STATUS_APPROVED])) {
            throw new InvalidArgumentException('Only pending or approved transfers can be cancelled.');
        }

        return DB::transaction(function () use ($transfer, $reason) {
            $transfer->transitionTo(InterCompanyTransfer::STATUS_CANCELLED, [
                'purpose' => $reason ? $transfer->purpose . " | Cancelled: {$reason}" : $transfer->purpose,
            ]);

            return $transfer->fresh();
        });
    }
}
