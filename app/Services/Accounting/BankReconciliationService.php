<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankMatchingRule;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\BankTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankReconciliationService
{
    /**
     * Create a new bank reconciliation session.
     */
    public function create(array $data, int $userId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $userId) {
            $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

            // Check for existing in-progress reconciliation (atomic: lockForUpdate prevents
            // two concurrent requests both passing this guard and creating duplicate sessions)
            $existing = BankReconciliation::where('bank_account_id', $bankAccount->id)
                ->where('organization_id', $data['organization_id'])
                ->where('status', BankReconciliation::STATUS_IN_PROGRESS)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new InvalidArgumentException(
                    'An in-progress reconciliation already exists for this bank account.'
                );
            }

            $reconciliation = BankReconciliation::create([
                'organization_id' => $data['organization_id'],
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $data['statement_date'],
                'statement_balance' => $data['statement_balance'],
                'book_balance' => $bankAccount->current_balance,
                'difference' => bcsub((string)$data['statement_balance'], (string)$bankAccount->current_balance, 4),
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $reconciliation->fresh(['bankAccount', 'createdBy']);
        });
    }

    /**
     * Auto-match bank transactions with internal transactions using matching rules.
     */
    public function autoMatch(BankReconciliation $reconciliation, int $userId): array
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Can only auto-match in-progress reconciliations.');
        }

        return DB::transaction(function () use ($reconciliation, $userId) {
            $bankAccount = $reconciliation->bankAccount;

            // Get unmatched bank transactions for this account up to statement date,
            // limited to within 90 days of the statement date to exclude stale transactions.
            $unmatchedTransactions = BankTransaction::where('bank_account_id', $bankAccount->id)
                ->where('organization_id', $reconciliation->organization_id)
                ->where('status', BankTransaction::STATUS_UNMATCHED)
                ->where('transaction_date', '<=', $reconciliation->statement_date)
                ->where('transaction_date', '>=', \Carbon\Carbon::parse($reconciliation->statement_date)->subDays(90))
                ->get();

            // Get matching rules for this account
            $rules = BankMatchingRule::where('organization_id', $reconciliation->organization_id)
                ->where(function ($q) use ($bankAccount) {
                    $q->whereNull('bank_account_id')
                        ->orWhere('bank_account_id', $bankAccount->id);
                })
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->get();

            $matched = 0;
            $excluded = 0;

            foreach ($unmatchedTransactions as $transaction) {
                $transactionData = [
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'transaction_type' => $transaction->transaction_type,
                ];

                foreach ($rules as $rule) {
                    if ($rule->matches($transactionData)) {
                        if ($rule->action === BankMatchingRule::ACTION_EXCLUDE) {
                            $transaction->update(['status' => BankTransaction::STATUS_EXCLUDED]);
                            $excluded++;
                        } else {
                            // Create reconciliation item
                            $reconciliation->items()->create([
                                'bank_transaction_id' => $transaction->id,
                                'item_type' => BankReconciliationItem::TYPE_BANK_TRANSACTION,
                                'transaction_date' => $transaction->transaction_date,
                                'reference' => $transaction->reference,
                                'description' => $transaction->description,
                                'amount' => $transaction->amount,
                                'is_cleared' => true,
                            ]);

                            $transaction->update([
                                'status' => BankTransaction::STATUS_MATCHED,
                                'category' => $rule->action_data['category'] ?? null,
                                'matched_at' => now(),
                                'matched_by' => $userId,
                            ]);
                            $matched++;
                        }
                        break; // Stop after first matching rule
                    }
                }
            }

            $reconciliation->calculateDifference();

            return [
                BankTransaction::STATUS_MATCHED => $matched,
                BankTransaction::STATUS_EXCLUDED => $excluded,
                BankTransaction::STATUS_UNMATCHED => $unmatchedTransactions->count() - $matched - $excluded,
            ];
        });
    }

    /**
     * Manually match a bank transaction to an internal transaction.
     */
    public function manualMatch(
        BankReconciliation $reconciliation,
        int $bankTransactionId,
        int $userId,
        ?int $matchedTransactionId = null,
        ?string $matchedTransactionType = null
    ): BankReconciliationItem {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Can only match in-progress reconciliations.');
        }

        return DB::transaction(function () use ($reconciliation, $bankTransactionId, $userId, $matchedTransactionId, $matchedTransactionType) {
            $bankTransaction = BankTransaction::findOrFail($bankTransactionId);

            if ($bankTransaction->status === BankTransaction::STATUS_MATCHED || $bankTransaction->status === BankTransaction::STATUS_RECONCILED) {
                throw new InvalidArgumentException('Bank transaction is already matched or reconciled.');
            }

            // Create reconciliation item
            $item = $reconciliation->items()->create([
                'bank_transaction_id' => $bankTransaction->id,
                'item_type' => BankReconciliationItem::TYPE_BANK_TRANSACTION,
                'transaction_date' => $bankTransaction->transaction_date,
                'reference' => $bankTransaction->reference,
                'description' => $bankTransaction->description,
                'amount' => $bankTransaction->amount,
                'is_cleared' => true,
            ]);

            // Update bank transaction status
            $bankTransaction->update([
                'status' => BankTransaction::STATUS_MATCHED,
                'matched_transaction_id' => $matchedTransactionId,
                'matched_transaction_type' => $matchedTransactionType,
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            $reconciliation->calculateDifference();

            return $item->load('bankTransaction');
        });
    }

    /**
     * Unmatch a previously matched bank transaction.
     */
    public function unmatch(BankReconciliation $reconciliation, int $itemId): void
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Can only unmatch in-progress reconciliations.');
        }

        DB::transaction(function () use ($reconciliation, $itemId) {
            $item = BankReconciliationItem::where('reconciliation_id', $reconciliation->id)
                ->findOrFail($itemId);

            // Reset bank transaction status — load instance so HasAuditTrail fires.
            if ($item->bank_transaction_id) {
                BankTransaction::find($item->bank_transaction_id)?->update([
                    'status' => BankTransaction::STATUS_UNMATCHED,
                    'matched_transaction_id' => null,
                    'matched_transaction_type' => null,
                    'matched_by' => null,
                    'matched_at' => null,
                ]);
            }

            $item->delete();
            $reconciliation->calculateDifference();
        });
    }

    /**
     * Complete a bank reconciliation.
     */
    public function complete(BankReconciliation $reconciliation, int $userId): BankReconciliation
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            throw new InvalidArgumentException('Can only complete in-progress reconciliations.');
        }

        if (!$reconciliation->isBalanced()) {
            throw new InvalidArgumentException(
                "Reconciliation is not balanced. Difference: {$reconciliation->difference}"
            );
        }

        return DB::transaction(function () use ($reconciliation, $userId) {
            BankReconciliation::where('id', $reconciliation->id)->lockForUpdate()->first();

            // Fix 9: Assert the accounting period is not locked before posting the reconciliation.
            $statementDate = is_string($reconciliation->statement_date)
                ? $reconciliation->statement_date
                : $reconciliation->statement_date->toDateString();
            app(\App\Services\Accounting\PeriodLockService::class)
                ->assertNotLocked($reconciliation->organization_id, $statementDate, $userId);

            // Mark all matched bank transactions as reconciled
            $reconciliation->items()
                ->where('is_cleared', true)
                ->whereNotNull('bank_transaction_id')
                ->get()
                ->each(function ($item) {
                    BankTransaction::where('id', $item->bank_transaction_id)
                        ->update(['status' => BankTransaction::STATUS_RECONCILED]);
                });

            // Build summary
            $summary = [
                'total_items' => $reconciliation->items()->count(),
                'cleared_items' => $reconciliation->items()->where('is_cleared', true)->count(),
                'total_cleared_amount' => (float) $reconciliation->items()->where('is_cleared', true)->sum('amount'),
            ];

            // Update reconciliation
            $reconciliation->update([
                'status' => BankReconciliation::STATUS_COMPLETED,
                'summary' => $summary,
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);

            // Update bank account
            $reconciliation->bankAccount->update([
                'last_reconciled_date' => $reconciliation->statement_date,
                'last_reconciled_balance' => $reconciliation->statement_balance,
            ]);

            return $reconciliation->fresh(['bankAccount', 'items', 'completedBy']);
        });
    }

    /**
     * Import a bank statement file.
     */
    public function importStatement(array $data): BankStatementImport
    {
        return DB::transaction(function () use ($data) {
            $import = BankStatementImport::create([
                'organization_id' => $data['organization_id'],
                'bank_account_id' => $data['bank_account_id'],
                'user_id' => $data['user_id'] ?? auth()->id(),
                'file_name' => $data['file_name'],
                'file_path' => $data['file_path'],
                'file_type' => $data['file_type'],
                'statement_start_date' => $data['statement_start_date'] ?? null,
                'statement_end_date' => $data['statement_end_date'] ?? null,
                'total_transactions' => $data['total_transactions'] ?? 0,
            ]);

            // If transactions data is provided, process them immediately
            if (!empty($data['transactions'])) {
                $import->markProcessing();
                $imported = 0;
                $duplicates = 0;

                foreach ($data['transactions'] as $txn) {
                    // Check for duplicates
                    $exists = BankTransaction::where('bank_account_id', $data['bank_account_id'])
                        ->where('transaction_date', $txn['transaction_date'])
                        ->where('amount', $txn['amount'])
                        ->where('description', $txn['description'])
                        ->exists();

                    if ($exists) {
                        $duplicates++;
                        continue;
                    }

                    BankTransaction::create(array_merge($txn, [
                        'organization_id' => $data['organization_id'],
                        'bank_account_id' => $data['bank_account_id'],
                        'import_source' => $data['file_type'],
                        'import_batch_id' => (string) $import->uuid,
                    ]));
                    $imported++;
                }

                $import->update(['total_transactions' => count($data['transactions'])]);
                $import->markCompleted($imported, $duplicates);
            }

            return $import->fresh(['bankAccount', 'user']);
        });
    }
}
