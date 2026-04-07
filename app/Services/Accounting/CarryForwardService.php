<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountOpeningBalance;
use App\Models\Accounting\CarryForwardRun;
use App\Models\Accounting\FiscalYear;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CarryForwardService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Execute year-end carry forward for balance sheet accounts.
     * Creates opening balances in the target fiscal year.
     */
    public function executeCarryForward(array $data): CarryForwardRun
    {
        $fromFy = FiscalYear::findOrFail($data['from_fiscal_year_id']);
        $toFy   = FiscalYear::findOrFail($data['to_fiscal_year_id']);

        if ($fromFy->organization_id !== $toFy->organization_id) {
            throw new InvalidArgumentException('Fiscal years must belong to the same organization.');
        }

        if (!$fromFy->is_closed) {
            throw new InvalidArgumentException('The source fiscal year must be closed before carry forward.');
        }

        $run = CarryForwardRun::create([
            'organization_id'      => $fromFy->organization_id,
            'from_fiscal_year_id'  => $fromFy->id,
            'to_fiscal_year_id'    => $toFy->id,
            'run_type'             => $data['run_type'] ?? CarryForwardRun::RUN_TYPE_BOTH,
            'status'               => CarryForwardRun::STATUS_RUNNING,
            'executed_by'          => $data['executed_by'],
        ]);

        try {
            [$accountsProcessed, $totalCarried] = $this->performCarryForward($run, $fromFy, $toFy);

            $run->update([
                'status'               => CarryForwardRun::STATUS_COMPLETED,
                'accounts_processed'   => $accountsProcessed,
                'total_amount_carried' => $totalCarried,
                'executed_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status'    => CarryForwardRun::STATUS_FAILED,
                'error_log' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $run->fresh(['fromFiscalYear', 'toFiscalYear', 'executedBy']);
    }

    /**
     * Return the status and details of a carry forward run.
     */
    public function getCarryForwardStatus(CarryForwardRun $run): CarryForwardRun
    {
        return $run->load(['fromFiscalYear', 'toFiscalYear', 'executedBy']);
    }

    /**
     * Internal: iterate accounts, calculate closing balances, write opening balances.
     */
    private function performCarryForward(
        CarryForwardRun $run,
        FiscalYear $fromFy,
        FiscalYear $toFy
    ): array {
        $runType = $run->run_type;

        // Determine which account types to carry forward
        $accountTypes = match ($runType) {
            CarryForwardRun::RUN_TYPE_BALANCE_SHEET => [Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY],
            CarryForwardRun::RUN_TYPE_PROFIT_LOSS   => [Account::TYPE_INCOME, Account::TYPE_EXPENSE],
            default                                  => [
                Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY,
                Account::TYPE_INCOME, Account::TYPE_EXPENSE,
            ],
        };

        $accounts = Account::where('organization_id', $fromFy->organization_id)
            ->whereIn('account_type', $accountTypes)
            ->where('is_header', false)
            ->where('is_active', true)
            ->get();

        $accountsProcessed = 0;
        $totalCarried = 0.0;

        DB::transaction(function () use ($accounts, $fromFy, $toFy, &$accountsProcessed, &$totalCarried) {
            foreach ($accounts as $account) {
                $closingBalance = $account->getTotalBalance($fromFy->id);

                if ($closingBalance == 0) {
                    continue;
                }

                $isDebitNormal = $account->isDebitNormal();
                $debit  = $isDebitNormal ? max(0, $closingBalance) : 0;
                $credit = $isDebitNormal ? 0 : max(0, $closingBalance);

                // Upsert opening balance for the target fiscal year
                AccountOpeningBalance::updateOrCreate(
                    [
                        'organization_id' => $toFy->organization_id,
                        'fiscal_year_id'  => $toFy->id,
                        'account_id'      => $account->id,
                    ],
                    [
                        'debit'  => $debit,
                        'credit' => $credit,
                    ]
                );

                $accountsProcessed++;
                $totalCarried += abs($closingBalance);
            }
        });

        return [$accountsProcessed, $totalCarried];
    }
}
