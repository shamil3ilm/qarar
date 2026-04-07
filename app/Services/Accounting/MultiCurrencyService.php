<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Accounting\CurrencyRevaluationItem;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\ForexGainLossEntry;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\OrganizationCurrency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MultiCurrencyService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}
    /**
     * Add a currency to an organization.
     */
    public function addCurrency(array $data): OrganizationCurrency
    {
        return DB::transaction(function () use ($data) {
            // Check if already exists
            $existing = OrganizationCurrency::withoutGlobalScopes()
                ->where('organization_id', $data['organization_id'])
                ->where('currency_code', $data['currency_code'])
                ->first();

            if ($existing) {
                // Reactivate if was deactivated
                $existing->update(['is_active' => true]);
                return $existing->fresh();
            }

            return OrganizationCurrency::create($data);
        });
    }

    /**
     * Remove (deactivate) a currency from an organization.
     */
    public function removeCurrency(int $organizationId, string $currencyCode): OrganizationCurrency
    {
        $orgCurrency = OrganizationCurrency::where('organization_id', $organizationId)->where('currency_code', $currencyCode)->firstOrFail();

        if ($orgCurrency->is_base_currency) {
            throw new InvalidArgumentException('Cannot remove the base currency.');
        }

        $orgCurrency->update(['is_active' => false]);

        return $orgCurrency->fresh();
    }

    /**
     * Get all currencies for an organization.
     */
    public function getOrgCurrencies(int $organizationId, bool $activeOnly = true): Collection
    {
        $query = OrganizationCurrency::with(['currency', 'exchangeGainAccount', 'exchangeLossAccount'])
            ->where('organization_id', $organizationId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Create and calculate a currency revaluation.
     */
    public function revalue(array $data, array $accounts): CurrencyRevaluation
    {
        return DB::transaction(function () use ($data, $accounts) {
            $revaluation = CurrencyRevaluation::create($data);

            foreach ($accounts as $accountData) {
                $foreignBalance = (string) $accountData['foreign_currency_balance'];
                $oldBaseAmount = bcmul($foreignBalance, (string) $data['old_rate'], 4);
                $newBaseAmount = bcmul($foreignBalance, (string) $data['new_rate'], 4);
                $gainLoss = bcsub($newBaseAmount, $oldBaseAmount, 4);

                $revaluation->items()->create([
                    'account_id' => $accountData['account_id'],
                    'account_type' => $accountData['account_type'],
                    'foreign_currency_balance' => $foreignBalance,
                    'old_base_amount' => $oldBaseAmount,
                    'new_base_amount' => $newBaseAmount,
                    'gain_loss_amount' => $gainLoss,
                    'contact_id' => $accountData['contact_id'] ?? null,
                ]);
            }

            $revaluation->recalculateTotals();

            return $revaluation->fresh(['items', 'items.account']);
        });
    }

    /**
     * Post a revaluation (create journal entries).
     */
    public function postRevaluation(CurrencyRevaluation $revaluation, ?int $gainLossAccountId = null): CurrencyRevaluation
    {
        if (!$revaluation->canPost()) {
            throw new InvalidArgumentException('Revaluation cannot be posted. Ensure it is in draft status with items.');
        }

        return DB::transaction(function () use ($revaluation, $gainLossAccountId) {
            if ($gainLossAccountId) {
                $revaluation->gain_loss_account_id = $gainLossAccountId;
            }

            $revaluation->update([
                'status' => CurrencyRevaluation::STATUS_POSTED,
            ]);

            // Record forex gain/loss entries for each item (sub-ledger reporting)
            foreach ($revaluation->items as $item) {
                if (bccomp((string) $item->gain_loss_amount, '0', 4) !== 0) {
                    ForexGainLossEntry::create([
                        'organization_id' => $revaluation->organization_id,
                        'entry_type' => ForexGainLossEntry::TYPE_UNREALIZED,
                        'transaction_type' => ForexGainLossEntry::TRANSACTION_REVALUATION,
                        'source_type' => CurrencyRevaluation::class,
                        'source_id' => $revaluation->id,
                        'foreign_currency' => $revaluation->currency_code,
                        'base_currency' => $revaluation->base_currency,
                        'foreign_amount' => $item->foreign_currency_balance,
                        'original_rate' => $revaluation->old_rate,
                        'settlement_rate' => $revaluation->new_rate,
                        'gain_loss_amount' => $item->gain_loss_amount,
                        'account_id' => $item->account_id,
                        'transaction_date' => $revaluation->revaluation_date,
                    ]);
                }
            }

            // Fix 1: Post a balanced GL journal entry for the revaluation.
            // Skip entirely if the net gain/loss is zero (neutral revaluation).
            $netGainLoss = bcadd('0.0000', '0.0000', 4);
            foreach ($revaluation->items as $item) {
                $netGainLoss = bcadd($netGainLoss, (string) $item->gain_loss_amount, 4);
            }

            if (bccomp($netGainLoss, '0.0000', 4) !== 0) {
                $orgId = $revaluation->organization_id;

                // Resolve the forex gain/loss offset account.
                $offsetAccount = Account::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where(function ($q) {
                        $q->where('account_type', 'forex_gain_loss')
                          ->orWhere('name', 'like', '%Forex%')
                          ->orWhere('name', 'like', '%Exchange%');
                    })
                    ->where('is_active', true)
                    ->where('is_header', false)
                    ->orderBy('id')
                    ->first();

                if ($offsetAccount === null) {
                    // Fallback: use other_income for gains, other_expense for losses.
                    $fallbackType = bccomp($netGainLoss, '0.0000', 4) > 0
                        ? 'other_income'
                        : 'other_expense';
                    $offsetAccount = Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->where('account_type', $fallbackType)
                        ->where('is_active', true)
                        ->where('is_header', false)
                        ->orderBy('id')
                        ->first();
                }

                if ($offsetAccount === null) {
                    throw new \App\Exceptions\ApiException(
                        'Forex gain/loss account not configured. Set up a foreign exchange account before posting revaluations.'
                    );
                }

                if ($offsetAccount !== null) {
                    $journalLines = [];
                    foreach ($revaluation->items as $item) {
                        $gainLoss = (float) $item->gain_loss_amount;
                        if (abs($gainLoss) < 0.00005) {
                            continue;
                        }
                        // Debit/credit the A/R or A/P account for the adjustment amount.
                        $journalLines[] = [
                            'account_id' => $item->account_id,
                            'description' => "Forex revaluation: {$revaluation->currency_code}",
                            'debit' => $gainLoss > 0 ? $gainLoss : 0,
                            'credit' => $gainLoss < 0 ? abs($gainLoss) : 0,
                            'line_order' => count($journalLines),
                        ];
                    }
                    // Offset entry to the forex gain/loss account.
                    $netFloat = (float) $netGainLoss;
                    $journalLines[] = [
                        'account_id' => $offsetAccount->id,
                        'description' => "Forex revaluation offset: {$revaluation->currency_code}",
                        'debit' => $netFloat < 0 ? abs($netFloat) : 0,
                        'credit' => $netFloat > 0 ? $netFloat : 0,
                        'line_order' => count($journalLines),
                    ];

                    $journalEntry = $this->journalService->createEntry(
                        [
                            'organization_id' => $orgId,
                            'entry_date' => $revaluation->revaluation_date,
                            'reference' => 'FOREX-REVAL-' . $revaluation->id,
                            'description' => "Currency revaluation: {$revaluation->currency_code} "
                                . "({$revaluation->old_rate} → {$revaluation->new_rate})",
                            'source_type' => CurrencyRevaluation::class,
                            'source_id' => $revaluation->id,
                        ],
                        $journalLines
                    );

                    $this->journalService->postEntry($journalEntry);
                }
            }

            return $revaluation->fresh(['items']);
        });
    }

    /**
     * Reverse a posted revaluation.
     */
    public function reverseRevaluation(CurrencyRevaluation $revaluation): CurrencyRevaluation
    {
        if (!$revaluation->canReverse()) {
            throw new InvalidArgumentException('Only posted revaluations can be reversed.');
        }

        return DB::transaction(function () use ($revaluation) {
            // Delete associated forex entries
            ForexGainLossEntry::where('source_type', CurrencyRevaluation::class)
                ->where('source_id', $revaluation->id)
                ->delete();

            $revaluation->update([
                'status' => CurrencyRevaluation::STATUS_REVERSED,
            ]);

            if ($revaluation->journal_entry_id !== null) {
                $journalEntry = JournalEntry::find($revaluation->journal_entry_id);
                if ($journalEntry !== null) {
                    $this->journalService->reverseEntry($journalEntry, 'Currency revaluation reversed');
                }
            }

            return $revaluation->fresh();
        });
    }

    /**
     * Record a realized forex gain/loss (on payment/settlement).
     */
    public function recordForexGainLoss(array $data): ForexGainLossEntry
    {
        return DB::transaction(function () use ($data) {
            $data['entry_type'] = $data['entry_type'] ?? ForexGainLossEntry::TYPE_REALIZED;

            $foreignAmount = (string) $data['foreign_amount'];
            $originalRate = (string) $data['original_rate'];
            $settlementRate = (string) $data['settlement_rate'];

            $data['gain_loss_amount'] = $data['gain_loss_amount']
                ?? bcmul($foreignAmount, bcsub($settlementRate, $originalRate, 8), 4);

            return ForexGainLossEntry::create($data);
        });
    }

    /**
     * Get exchange gain/loss report.
     */
    public function getExchangeGainLossReport(
        int $organizationId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $currency = null,
        ?string $entryType = null
    ): array {
        $query = ForexGainLossEntry::withoutGlobalScopes()
            ->where('organization_id', $organizationId);

        if ($fromDate && $toDate) {
            $query->whereBetween('transaction_date', [$fromDate, $toDate]);
        }

        if ($currency) {
            $query->where('foreign_currency', $currency);
        }

        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        $entries = $query->orderBy('transaction_date')->get();

        $totalGains = $entries->where('gain_loss_amount', '>', 0)->sum('gain_loss_amount');
        $totalLosses = $entries->where('gain_loss_amount', '<', 0)->sum('gain_loss_amount');

        $totalGainsStr = bcadd((string) $totalGains, '0', 4);
        $totalLossesStr = bcadd((string) $totalLosses, '0', 4);
        // Absolute value: strip leading minus if present
        $totalLossesAbs = bccomp($totalLossesStr, '0', 4) < 0
            ? bcsub('0', $totalLossesStr, 4)
            : $totalLossesStr;

        return [
            'entries' => $entries,
            'summary' => [
                'total_gains' => (float) $totalGainsStr,
                'total_losses' => (float) $totalLossesAbs,
                'net_gain_loss' => (float) bcadd($totalGainsStr, $totalLossesStr, 4),
                'count' => $entries->count(),
            ],
        ];
    }

    /**
     * Auto-run period-end FX revaluation (SAP F.05 equivalent).
     *
     * Scans all GL accounts configured with a foreign currency and computes
     * their current foreign-currency balance from posted journal entry lines.
     * Builds the accounts array automatically and calls revalue().
     *
     * @param  int     $organizationId
     * @param  string  $revaluationDate  ISO date (e.g. "2026-03-31")
     * @param  string  $baseCurrency     Base currency code (e.g. "SAR")
     * @param  bool    $autoPost         If true, immediately post after creating
     * @return CurrencyRevaluation
     */
    public function autoRun(
        int $organizationId,
        string $revaluationDate,
        string $baseCurrency = 'SAR',
        bool $autoPost = false,
        ?int $createdBy = null,
    ): CurrencyRevaluation {
        // 1. Find all GL accounts with a configured foreign currency (currency_code != base currency).
        $foreignAccounts = Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotNull('currency_code')
            ->where('currency_code', '!=', $baseCurrency)
            ->where('is_active', true)
            ->where('is_header', false)
            ->select(['id', 'account_type', 'currency_code'])
            ->get();

        if ($foreignAccounts->isEmpty()) {
            throw new InvalidArgumentException('No foreign-currency GL accounts found for this organization.');
        }

        // 2. Group by currency so we do one revaluation per currency.
        $byCurrency = $foreignAccounts->groupBy('currency_code');

        $revaluations = [];

        foreach ($byCurrency as $currencyCode => $accounts) {
            // 3. Get the current exchange rate for this currency.
            $newRate = ExchangeRate::getRate($organizationId, $currencyCode, $baseCurrency, $revaluationDate);
            if ($newRate === null) {
                // Skip currencies with no rate configured — don't fail the whole run.
                continue;
            }

            // 4. Get the previous rate (most recent posted revaluation for this currency).
            $lastRevaluation = CurrencyRevaluation::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('currency_code', $currencyCode)
                ->where('base_currency', $baseCurrency)
                ->where('status', CurrencyRevaluation::STATUS_POSTED)
                ->orderByDesc('revaluation_date')
                ->first();

            $oldRate = $lastRevaluation ? (float) $lastRevaluation->new_rate : 0;

            // 5. Compute foreign-currency balance for each account from posted journal lines.
            $accountsData = [];
            foreach ($accounts as $account) {
                $balance = DB::table('journal_entry_lines as jel')
                    ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                    ->where('je.organization_id', $organizationId)
                    ->where('jel.account_id', $account->id)
                    ->where('je.status', 'posted')
                    ->selectRaw('COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0) as net_balance')
                    ->value('net_balance') ?? 0;

                // Only include accounts with a non-zero balance.
                if (abs((float) $balance) < 0.00005) {
                    continue;
                }

                $accountType = match ($account->account_type) {
                    'asset'     => 'asset',
                    'liability' => 'liability',
                    default     => 'asset',
                };

                $accountsData[] = [
                    'account_id'               => $account->id,
                    'account_type'             => $accountType,
                    'foreign_currency_balance' => (float) $balance,
                ];
            }

            if (empty($accountsData)) {
                continue;
            }

            $revaluationData = [
                'organization_id'    => $organizationId,
                'revaluation_date'   => $revaluationDate,
                'currency_code'      => $currencyCode,
                'base_currency'      => $baseCurrency,
                'old_rate'           => $oldRate,
                'new_rate'           => $newRate,
                'notes'              => "Auto-run F.05 period-end revaluation for {$currencyCode}",
                'created_by'         => $createdBy,
            ];

            $revaluation = $this->revalue($revaluationData, $accountsData);

            if ($autoPost) {
                $revaluation = $this->postRevaluation($revaluation);
            }

            $revaluations[] = $revaluation;
        }

        if (empty($revaluations)) {
            throw new InvalidArgumentException('No accounts with non-zero foreign-currency balances found to revalue.');
        }

        // Return the first (or only) revaluation; caller iterates the result if multi-currency.
        return $revaluations[0];
    }

    /**
     * Convert an amount from one currency to another using organization exchange rates.
     */
    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        int $organizationId,
        ?string $date = null
    ): ?float {
        return ExchangeRate::convert($amount, $fromCurrency, $toCurrency, $organizationId, $date);
    }
}
