<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountBalanceSnapshot;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateAccountBalanceSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int $organizationId,
        private readonly int $accountId,
    ) {}

    public function handle(): void
    {
        $account = Account::where('organization_id', $this->organizationId)
            ->findOrFail($this->accountId);

        $lines = JournalEntryLine::where('account_id', $this->accountId)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('organization_id', $this->organizationId)
                ->where('status', 'posted')
            );

        $debitTotal  = (float) $lines->clone()->sum('debit');
        $creditTotal = (float) $lines->clone()->sum('credit');

        // Normal balance convention: asset/expense = debit-credit, else credit-debit
        $balance = in_array($account->account_type, ['asset', 'expense'], true)
            ? $debitTotal - $creditTotal
            : $creditTotal - $debitTotal;

        AccountBalanceSnapshot::upsertForAccount($this->organizationId, $this->accountId, [
            'balance'      => $balance,
            'debit_total'  => $debitTotal,
            'credit_total' => $creditTotal,
        ]);
    }
}
