<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Finance\PettyCashFund;
use App\Models\Finance\PettyCashReplenishment;
use App\Models\Finance\PettyCashVoucher;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PettyCashService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Create a new petty cash voucher (draft).
     */
    public function createVoucher(PettyCashFund $fund, array $data): PettyCashVoucher
    {
        if (!$fund->is_active) {
            throw new InvalidArgumentException('Cannot create voucher for an inactive fund.');
        }

        $amount = (float) $data['amount'];

        if (bccomp((string)$amount, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Voucher amount must be positive.');
        }

        if ($fund->max_transaction_limit > 0 && $amount > (float) $fund->max_transaction_limit) {
            throw new InvalidArgumentException(
                "Amount {$amount} exceeds the maximum transaction limit of {$fund->max_transaction_limit}."
            );
        }

        $voucherNumber = $this->numberGenerator->generate(
            $fund->organization_id ?? $data['organization_id'],
            'petty_cash_voucher'
        );

        return PettyCashVoucher::create([
            'fund_id'          => $fund->id,
            'voucher_number'   => $voucherNumber,
            'voucher_date'     => $data['voucher_date'] ?? now()->toDateString(),
            'transaction_type' => $data['transaction_type'],
            'amount'           => $amount,
            'description'      => $data['description'],
            'category'         => $data['category'] ?? null,
            'payee_payer'      => $data['payee_payer'] ?? null,
            'receipt_number'   => $data['receipt_number'] ?? null,
            'account_id'       => $data['account_id'] ?? null,
            'status'           => PettyCashVoucher::STATUS_DRAFT,
            'created_by'       => auth()->id(),
        ]);
    }

    /**
     * Approve a draft voucher.
     */
    public function approveVoucher(PettyCashVoucher $voucher): PettyCashVoucher
    {
        if (!$voucher->isDraft()) {
            throw new InvalidArgumentException('Only draft vouchers can be approved.');
        }

        $voucher->transitionTo(PettyCashVoucher::STATUS_APPROVED, [
            'approved_by' => auth()->id(),
        ]);

        return $voucher->fresh();
    }

    /**
     * Post an approved voucher, adjusting the fund balance.
     */
    public function postVoucher(PettyCashVoucher $voucher): PettyCashVoucher
    {
        if (!$voucher->isApproved()) {
            throw new InvalidArgumentException('Only approved vouchers can be posted.');
        }

        return DB::transaction(function () use ($voucher) {
            $fund   = PettyCashFund::lockForUpdate()->findOrFail($voucher->fund_id);
            $amount = (float) $voucher->amount;

            if ($voucher->transaction_type === PettyCashVoucher::TYPE_PAYMENT) {
                if ($amount > (float) $fund->current_balance) {
                    throw new InvalidArgumentException(
                        "Insufficient fund balance. Available: {$fund->current_balance}, Required: {$amount}."
                    );
                }
                $newBalance = bcsub((string) $fund->current_balance, (string) $amount, 4);
            } else {
                $newBalance = bcadd((string) $fund->current_balance, (string) $amount, 4);
            }

            $fund->update(['current_balance' => $newBalance]);

            $voucher->transitionTo(PettyCashVoucher::STATUS_POSTED);

            // Fix 2: Post a GL journal entry for the voucher expenditure.
            $expenseAccountId = $voucher->expense_account_id ?? null;
            $glAccountId = $fund->gl_account_id ?? null;

            if ($expenseAccountId === null || $glAccountId === null) {
                \Illuminate\Support\Facades\Log::warning(
                    'PettyCashService::postVoucher - skipping GL journal entry due to missing account '
                    . "configuration. Voucher ID: {$voucher->id}, "
                    . "expense_account_id: " . ($expenseAccountId ?? 'null') . ', '
                    . "gl_account_id: " . ($glAccountId ?? 'null') . '.'
                );
            } else {
                $journalEntry = $this->journalService->createEntry(
                    [
                        'organization_id' => $fund->organization_id,
                        'entry_date' => $voucher->voucher_date ?? now()->toDateString(),
                        'reference' => 'PCF-' . $voucher->id,
                        'description' => $voucher->description ?? "Petty cash voucher #{$voucher->voucher_number}",
                    ],
                    [
                        [
                            'account_id' => $expenseAccountId,
                            'description' => "Expense: " . ($voucher->description ?? $voucher->voucher_number),
                            'debit' => (float) $voucher->amount,
                            'credit' => 0,
                            'line_order' => 0,
                        ],
                        [
                            'account_id' => $glAccountId,
                            'description' => "Petty cash fund: {$fund->name}",
                            'debit' => 0,
                            'credit' => (float) $voucher->amount,
                            'line_order' => 1,
                        ],
                    ]
                );
                $this->journalService->postEntry($journalEntry);
            }

            return $voucher->fresh();
        });
    }

    /**
     * Request fund replenishment.
     */
    public function requestReplenishment(PettyCashFund $fund, float $amount, ?string $notes = null): PettyCashReplenishment
    {
        if (!$fund->is_active) {
            throw new InvalidArgumentException('Cannot replenish an inactive fund.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Replenishment amount must be positive.');
        }

        return PettyCashReplenishment::create([
            'fund_id'             => $fund->id,
            'replenishment_date'  => now()->toDateString(),
            'amount'              => $amount,
            'notes'               => $notes,
            'requested_by'        => auth()->id(),
            'status'              => PettyCashReplenishment::STATUS_REQUESTED,
        ]);
    }

    /**
     * Approve a replenishment request.
     */
    public function approveReplenishment(PettyCashReplenishment $replenishment): PettyCashReplenishment
    {
        if (!$replenishment->isRequested()) {
            throw new InvalidArgumentException('Only requested replenishments can be approved.');
        }

        $replenishment->transitionTo(PettyCashReplenishment::STATUS_APPROVED, [
            'approved_by' => auth()->id(),
        ]);

        return $replenishment->fresh();
    }

    /**
     * Disburse an approved replenishment, updating the fund balance.
     */
    public function disburseReplenishment(PettyCashReplenishment $replenishment): PettyCashReplenishment
    {
        if (!$replenishment->isApproved()) {
            throw new InvalidArgumentException('Only approved replenishments can be disbursed.');
        }

        return DB::transaction(function () use ($replenishment) {
            $fund       = $replenishment->fund;
            $newBalance = bcadd((string) $fund->current_balance, (string) $replenishment->amount, 4);

            $fund->update(['current_balance' => $newBalance]);

            $replenishment->transitionTo(PettyCashReplenishment::STATUS_DISBURSED);

            return $replenishment->fresh();
        });
    }
}
