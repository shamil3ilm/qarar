<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Budget\BudgetLine;
use App\Services\Core\CacheService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates, posts, voids, and reverses double-entry journal entries in the general ledger.
 *
 * Responsibilities:
 * - Validate that entry lines are balanced (sum of debits equals sum of credits)
 * - Validate individual lines: non-negative amounts, no mixed debit+credit, postable
 *   account (non-header, active), minimum two lines per entry
 * - Auto-resolve the fiscal year for an entry date, creating an initial year when
 *   no fiscal years exist yet; reject entries that fall in a closed year or period
 * - Enforce SAP CO-style budget availability: hard-block debit lines on expense
 *   accounts that would exceed the approved budget for a cost center
 * - Post draft entries, void posted entries, and create mirror-image reversal entries
 * - Bust per-account balance caches via CacheService after a successful post
 * - Provide convenience factories: createFromSource() for document-linked entries,
 *   createSimpleEntry() for two-line transfers
 *
 * Side Effects:
 * - Writes JournalEntry and JournalEntryLine rows to the database
 * - Calls CacheService.bustAccountBalance() for every account line on post
 * - Re-validates the persisted balance after line creation to catch partial-insert anomalies
 *
 * Idempotency:
 * - createEntry() is NOT idempotent; each call persists a new entry row
 * - postEntry() throws if the entry is already posted (status guard)
 * - voidEntry() / reverseEntry() throw if the entry is not in STATUS_POSTED,
 *   and reverseEntry() additionally throws if reversed_by_id is already set
 *
 * CONTRACT:
 * - All write methods must be called inside a DB::transaction; createEntry() wraps
 *   its own transaction but nesting is safe under InnoDB savepoints
 * - Debits must equal credits before calling createEntry(); the service will throw
 *   InvalidArgumentException if the lines are unbalanced
 * - Account IDs in lines must belong to the same organization as the entry; the
 *   service does not currently cross-check tenancy on individual account records
 * - postEntry() requires that the fiscal year and accounting period for the entry
 *   date are both open; closed periods cause an InvalidArgumentException
 */
class JournalService
{
    public function __construct(
        private readonly CacheService $cache,
    ) {}

    /**
     * Create a journal entry with lines (alias for createEntry).
     */
    public function create(array $entryData, array $lines): JournalEntry
    {
        return $this->createEntry($entryData, $lines);
    }

    /**
     * Create a journal entry with lines.
     *
     * @param array $entryData Entry header data
     * @param array $lines Array of line items [['account_id' => X, 'debit' => 0, 'credit' => 100], ...]
     * @return JournalEntry
     * @throws InvalidArgumentException
     */
    public function createEntry(array $entryData, array $lines): JournalEntry
    {
        $lines = $this->resolveAccountCodes($lines, $entryData['organization_id'] ?? auth()->user()?->organization_id);
        $this->validateLines($lines);

        return DB::transaction(function () use ($entryData, $lines) {
            // Set fiscal year if not provided
            if (!isset($entryData['fiscal_year_id'])) {
                $organizationId = $entryData['organization_id'] ?? auth()->user()?->organization_id;
                $entryDate = $entryData['entry_date'] ?? now();

                if ($organizationId) {
                    $fiscalYear = FiscalYear::forDate($organizationId, $entryDate);

                    // Auto-create fiscal year only when no fiscal years exist yet for this organization
                    if (!$fiscalYear) {
                        $hasAnyFiscalYear = FiscalYear::withoutGlobalScopes()
                            ->where('organization_id', $organizationId)
                            ->exists();

                        if ($hasAnyFiscalYear) {
                            throw new InvalidArgumentException('Entry date does not fall within any open fiscal year.');
                        }

                        $date = is_string($entryDate) ? now()->parse($entryDate) : $entryDate;
                        $org = \App\Models\Core\Organization::find($organizationId);
                        $startMonth = $org?->fiscal_year_start_month ?? 1;
                        $startDay = $org?->fiscal_year_start_day ?? 1;

                        $yearStart = $date->copy()->setMonth($startMonth)->setDay($startDay)->startOfDay();
                        if ($yearStart->gt($date)) {
                            $yearStart->subYear();
                        }
                        $yearEnd = $yearStart->copy()->addYear()->subDay()->endOfDay();

                        $fiscalYear = FiscalYear::create([
                            'organization_id' => $organizationId,
                            'name' => 'FY ' . $yearStart->format('Y') . '-' . $yearEnd->format('Y'),
                            'start_date' => $yearStart,
                            'end_date' => $yearEnd,
                            'is_closed' => false,
                        ]);
                    }

                    if ($fiscalYear->is_closed) {
                        throw new InvalidArgumentException('Cannot create entry in a closed fiscal year.');
                    }

                    $entryData['fiscal_year_id'] = $fiscalYear->id;

                    // Fix 7: Also check if the specific accounting sub-period is closed.
                    $entryDateStr = is_string($entryDate) ? $entryDate : $entryDate->toDateString();
                    $period = AccountingPeriod::withoutGlobalScopes()
                        ->where('organization_id', $organizationId)
                        ->where('start_date', '<=', $entryDateStr)
                        ->where('end_date', '>=', $entryDateStr)
                        ->first();
                    if ($period && $period->is_closed) {
                        throw new \App\Exceptions\ERP\ValidationException(
                            'Cannot post to a closed accounting period.'
                        );
                    }

                    // Fix 9: Assert the period is not locked for the current user.
                    $userId = $entryData['created_by'] ?? auth()->id();
                    if ($userId) {
                        app(\App\Services\Accounting\PeriodLockService::class)
                            ->assertNotLocked($organizationId, $entryDateStr, $userId);
                    }
                }
            }

            // Validate the explicitly-provided fiscal year before any DB write
            if (isset($entryData['fiscal_year_id'])) {
                $organizationId = $entryData['organization_id'] ?? auth()->user()?->organization_id;
                $fiscalYear = FiscalYear::withoutGlobalScopes()
                    ->where('id', $entryData['fiscal_year_id'])
                    ->first();

                if (!$fiscalYear || ($organizationId && (int) $fiscalYear->organization_id !== (int) $organizationId)) {
                    throw new InvalidArgumentException('Fiscal year not found or does not belong to this organization.');
                }

                if ($fiscalYear->is_closed) {
                    throw new InvalidArgumentException('Cannot create entry in a closed fiscal year.');
                }
            }

            // Create the entry
            $entry = JournalEntry::create($entryData);

            // Create lines
            foreach ($lines as $index => $lineData) {
                $entry->lines()->create([
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'cost_center_id' => $lineData['cost_center_id'] ?? null,
                    'contact_id' => $lineData['contact_id'] ?? null,
                    'line_order' => $lineData['line_order'] ?? $index,
                ]);
            }

            // Re-validate balance after all lines are persisted to catch any partial-insert anomaly.
            $entry->refresh();
            $totalDebits = $entry->lines()->sum('debit');
            $totalCredits = $entry->lines()->sum('credit');
            if (bccomp((string) $totalDebits, (string) $totalCredits, 4) !== 0) {
                throw new \App\Exceptions\ApiException('Journal entry is unbalanced after line creation.');
            }

            return $entry->fresh(['lines', 'lines.account']);
        });
    }

    /**
     * Create a journal entry from a source document (invoice, bill, payment).
     */
    public function createFromSource(
        object $source,
        string $sourceType,
        array $lines,
        ?string $description = null
    ): JournalEntry {
        return $this->createEntry([
            'organization_id' => $source->organization_id,
            'branch_id' => $source->branch_id,
            'entry_date' => $source->created_at->toDateString(),
            'reference' => $source->number ?? $source->reference ?? null,
            'description' => $description ?? "Entry from {$sourceType}",
            'source_type' => $sourceType,
            'source_id' => $source->id,
            'currency_code' => $source->currency_code ?? 'SAR',
            'exchange_rate' => $source->exchange_rate ?? 1,
        ], $lines);
    }

    /**
     * Post a draft journal entry.
     */
    public function postEntry(JournalEntry $entry): bool
    {
        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft entries can be posted.');
        }

        if ($entry->fiscalYear && $entry->fiscalYear->is_closed) {
            throw new InvalidArgumentException('Cannot post entry in a closed fiscal year.');
        }

        if (!$entry->isBalanced()) {
            throw new InvalidArgumentException(
                "Journal entry is not balanced. Debit: {$entry->total_debit}, Credit: {$entry->total_credit}"
            );
        }

        $this->checkBudgetAvailability($entry);

        if (!$entry->post()) {
            throw new \RuntimeException('Journal entry could not be posted. The fiscal year or period may be closed.');
        }

        // Bust account balance caches for every account touched by this entry.
        $orgId = (int) $entry->organization_id;
        foreach ($entry->lines as $line) {
            $this->cache->bustAccountBalance($orgId, (int) $line->account_id);
        }

        return true;
    }

    /**
     * SAP CO budget availability check (hard enforcement).
     *
     * For each debit line with a cost_center_id, find the active/approved
     * BudgetLine covering the entry date for that account + cost center.
     * Throw if committed + actual + this debit would exceed the approved amount.
     */
    private function checkBudgetAvailability(JournalEntry $entry): void
    {
        $entryDate = $entry->entry_date instanceof \DateTimeInterface
            ? $entry->entry_date->format('Y-m-d')
            : (string) $entry->entry_date;

        $lines = $entry->lines()->with('account:id,type')->get();

        foreach ($lines as $line) {
            // Only check expense accounts with a cost center assigned.
            if (!$line->cost_center_id || (float) $line->debit <= 0) {
                continue;
            }
            if ($line->account && !in_array($line->account->type, ['expense', 'cost_of_goods'], true)) {
                continue;
            }

            $budgetLine = BudgetLine::whereHas('budget', function ($q) use ($entry, $entryDate): void {
                $q->where('organization_id', $entry->organization_id)
                  ->whereIn('status', ['approved', 'active'])
                  ->where('period_start', '<=', $entryDate)
                  ->where('period_end', '>=', $entryDate);
            })
            ->where('account_id', $line->account_id)
            ->where('cost_center_id', $line->cost_center_id)
            ->lockForUpdate()
            ->first();

            if ($budgetLine === null) {
                continue; // No budget configured for this account/cost center — allow posting.
            }

            $available = $budgetLine->getAvailableAmount();

            if ((float) $line->debit > $available) {
                throw new InvalidArgumentException(
                    "Budget exceeded for account {$line->account_id} / cost center {$line->cost_center_id}. "
                    . "Available: {$available}, Requested: {$line->debit}."
                );
            }
        }
    }

    /**
     * Void a journal entry (alias for voidEntry).
     */
    public function void(JournalEntry $entry, string $reason): bool
    {
        return $this->voidEntry($entry, $reason);
    }

    /**
     * Void a posted journal entry.
     */
    public function voidEntry(JournalEntry $entry, string $reason): bool
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw new InvalidArgumentException('Only posted entries can be voided.');
        }

        return $entry->void($reason);
    }

    /**
     * Reverse a journal entry (alias for reverseEntry).
     */
    public function reverse(JournalEntry $entry, string $reason): JournalEntry
    {
        return $this->reverseEntry($entry, $reason);
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverseEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw new InvalidArgumentException('Only posted entries can be reversed.');
        }

        if ($entry->reversed_by_id) {
            throw new InvalidArgumentException('Entry has already been reversed.');
        }

        $reversal = $entry->reverse($reason);

        if (!$reversal) {
            throw new InvalidArgumentException('Failed to create reversal entry.');
        }

        return $reversal;
    }

    /**
     * Create a simple two-line journal entry (debit one account, credit another).
     */
    public function createSimpleEntry(
        int $organizationId,
        int $branchId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $description,
        ?string $reference = null,
        ?string $date = null
    ): JournalEntry {
        return $this->createEntry([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'entry_date' => $date ?? now()->toDateString(),
            'reference' => $reference,
            'description' => $description,
        ], [
            ['account_id' => $debitAccountId, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $amount],
        ]);
    }

    /**
     * Validate journal entry lines.
     */
    protected function resolveAccountCodes(array $lines, ?int $orgId): array
    {
        $codes = collect($lines)
            ->filter(fn($l) => isset($l['account_code']) && !isset($l['account_id']))
            ->pluck('account_code')
            ->unique()
            ->values()
            ->all();

        if (empty($codes)) {
            return $lines;
        }

        $query = Account::whereIn('code', $codes);
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }
        $codeMap = $query->pluck('id', 'code');

        return array_map(function (array $line) use ($codeMap): array {
            if (isset($line['account_code']) && !isset($line['account_id'])) {
                $line['account_id'] = $codeMap->get($line['account_code']);
                unset($line['account_code']);
            }
            return $line;
        }, $lines);
    }

    /**
     */
    protected function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Journal entry must have at least 2 lines.');
        }

        $totalDebit = '0.0000';
        $totalCredit = '0.0000';

        // Batch-load all referenced accounts in a single query to avoid N+1.
        $accountIds = collect($lines)->pluck('account_id')->filter()->unique()->values();
        $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');

        foreach ($lines as $index => $line) {
            if (!isset($line['account_id'])) {
                throw new InvalidArgumentException("Line {$index}: account_id is required.");
            }

            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;

            if (bccomp((string) $debit, '0', 4) < 0 || bccomp((string) $credit, '0', 4) < 0) {
                throw new InvalidArgumentException("Line {$index}: amounts cannot be negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException("Line {$index}: cannot have both debit and credit.");
            }

            if (bccomp((string) $debit, '0', 4) === 0 && bccomp((string) $credit, '0', 4) === 0) {
                throw new InvalidArgumentException("Line {$index}: must have either debit or credit.");
            }

            // Validate account exists and is postable using the pre-loaded map.
            $account = $accounts->get($line['account_id']);
            if (!$account) {
                throw new InvalidArgumentException("Line {$index}: account not found.");
            }

            if ($account->is_header) {
                throw new InvalidArgumentException("Line {$index}: cannot post to header account '{$account->name}'.");
            }

            if (!$account->is_active) {
                throw new InvalidArgumentException("Line {$index}: account '{$account->name}' is inactive.");
            }

            $totalDebit = bcadd($totalDebit, (string) $debit, 4);
            $totalCredit = bcadd($totalCredit, (string) $credit, 4);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new InvalidArgumentException(
                'Journal entry must be balanced. Debit: ' . $totalDebit . ', Credit: ' . $totalCredit
            );
        }
    }
}
