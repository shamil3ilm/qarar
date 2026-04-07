<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CoAssessmentPosting;
use App\Models\Accounting\CoDistributionPosting;
use App\Models\Accounting\CoReconciliationEntry;
use App\Models\Accounting\CoReconciliationRun;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CO Reconciliation Service — SAP KALC equivalent.
 *
 * When a CO allocation cycle (assessment or distribution) posts amounts that
 * cross organisational company-code boundaries, FI must receive matching
 * reconciliation journal entries so the general ledger stays in sync with CO.
 *
 * SAP performs this automatically via the reconciliation ledger (T-code KALC).
 * This service replicates that logic:
 *
 *  1. Detect cross-company postings in a CO run
 *  2. Group by sender / receiver company pair
 *  3. Generate FI debit/credit reconciliation entries (intercompany accounts)
 *  4. Persist a CoReconciliationRun for audit
 */
class CoReconciliationService
{
    /**
     * Create and post reconciliation entries for a completed assessment run.
     *
     * @param  int    $assessmentCycleId
     * @param  string $fiscalYear
     * @param  string $period   two-digit month, e.g. '03'
     * @param  User   $postedBy
     */
    public function reconcileAssessment(
        int $assessmentCycleId,
        string $fiscalYear,
        string $period,
        User $postedBy,
    ): ?CoReconciliationRun {
        $postings = CoAssessmentPosting::where('assessment_cycle_id', $assessmentCycleId)
            ->with(['senderCostCenter.organization', 'receiverCostCenter.organization'])
            ->get();

        return $this->processPostings('assessment', $assessmentCycleId, $postings, $fiscalYear, $period, $postedBy);
    }

    /**
     * Create and post reconciliation entries for a completed distribution run.
     */
    public function reconcileDistribution(
        int $distributionCycleId,
        string $fiscalYear,
        string $period,
        User $postedBy,
    ): ?CoReconciliationRun {
        $postings = CoDistributionPosting::where('distribution_cycle_id', $distributionCycleId)
            ->with(['senderCostCenter.organization', 'receiverCostCenter.organization'])
            ->get();

        return $this->processPostings('distribution', $distributionCycleId, $postings, $fiscalYear, $period, $postedBy);
    }

    /**
     * Get reconciliation runs for an organisation with optional filters.
     */
    public function getRuns(int $organizationId, array $filters = [])
    {
        $query = CoReconciliationRun::where('organization_id', $organizationId)
            ->with('entries');

        if (isset($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }

        if (isset($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(25);
    }

    // ----------------------------------------------------------------
    // Core logic
    // ----------------------------------------------------------------

    private function processPostings(
        string $sourceType,
        int $sourceId,
        $postings,
        string $fiscalYear,
        string $period,
        User $postedBy,
    ): ?CoReconciliationRun {
        // Filter to cross-company postings only
        $crossCompanyPostings = $postings->filter(function ($posting) {
            $senderOrgId   = $posting->senderCostCenter?->organization_id;
            $receiverOrgId = $posting->receiverCostCenter?->organization_id;
            return $senderOrgId !== null
                && $receiverOrgId !== null
                && $senderOrgId !== $receiverOrgId;
        });

        if ($crossCompanyPostings->isEmpty()) {
            return null; // Nothing to reconcile — all same company
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $crossCompanyPostings, $fiscalYear, $period, $postedBy): CoReconciliationRun {
            $organizationId = $postedBy->organization_id;

            $run = CoReconciliationRun::create([
                'organization_id' => $organizationId,
                'run_number'      => $this->generateRunNumber($organizationId),
                'source_type'     => $sourceType,
                'source_id'       => $sourceId,
                'fiscal_year'     => $fiscalYear,
                'period'          => $period,
                'status'          => CoReconciliationRun::STATUS_PENDING,
                'currency'        => 'SAR',
            ]);

            $totalAmount = 0.0;

            foreach ($crossCompanyPostings as $posting) {
                $amount = (float) $posting->amount;
                $totalAmount += $amount;

                // Generate FI journal entry for this cross-company reconciliation
                $je = $this->postReconciliationJournalEntry($posting, $run, $fiscalYear, $period, $postedBy);

                // Debit entry (sender side)
                CoReconciliationEntry::create([
                    'organization_id'          => $organizationId,
                    'reconciliation_run_id'    => $run->id,
                    'entry_type'               => 'debit',
                    'sender_company_id'        => $posting->senderCostCenter->organization_id,
                    'receiver_company_id'      => $posting->receiverCostCenter->organization_id,
                    'sender_cost_center_id'    => $posting->sender_cost_center_id,
                    'receiver_cost_center_id'  => $posting->receiver_cost_center_id,
                    'cost_element_id'          => $posting->cost_element_id,
                    'journal_entry_id'         => $je?->id,
                    'amount'                   => $amount,
                    'currency'                 => $posting->currency ?? 'SAR',
                    'description'              => "CO reconciliation: {$sourceType} #{$sourceId}",
                ]);

                // Credit entry (receiver side)
                CoReconciliationEntry::create([
                    'organization_id'          => $organizationId,
                    'reconciliation_run_id'    => $run->id,
                    'entry_type'               => 'credit',
                    'sender_company_id'        => $posting->senderCostCenter->organization_id,
                    'receiver_company_id'      => $posting->receiverCostCenter->organization_id,
                    'sender_cost_center_id'    => $posting->sender_cost_center_id,
                    'receiver_cost_center_id'  => $posting->receiver_cost_center_id,
                    'cost_element_id'          => $posting->cost_element_id,
                    'journal_entry_id'         => $je?->id,
                    'amount'                   => $amount,
                    'currency'                 => $posting->currency ?? 'SAR',
                    'description'              => "CO reconciliation: {$sourceType} #{$sourceId}",
                ]);
            }

            $run->update([
                'total_amount' => $totalAmount,
                'status'       => CoReconciliationRun::STATUS_POSTED,
                'posted_by'    => $postedBy->id,
                'posted_at'    => now(),
            ]);

            return $run->fresh(['entries']);
        });
    }

    /**
     * Post a FI journal entry for the cross-company reconciliation amount.
     * Uses the configured intercompany clearing accounts from Chart of Accounts.
     */
    private function postReconciliationJournalEntry(
        mixed $posting,
        CoReconciliationRun $run,
        string $fiscalYear,
        string $period,
        User $postedBy,
    ): ?JournalEntry {
        // Find intercompany clearing accounts (type = 'intercompany')
        $debitAccount  = Account::where('organization_id', $postedBy->organization_id)
            ->where('account_type', 'intercompany')
            ->where('is_active', true)
            ->first();

        $creditAccount = $debitAccount; // Same clearing account, debit one side / credit other

        if (! $debitAccount) {
            return null; // No intercompany account configured — skip FI posting but still record the run
        }

        $amount = (float) $posting->amount;

        $je = JournalEntry::create([
            'organization_id' => $postedBy->organization_id,
            'fiscal_year_id'  => null,
            'entry_date'      => now()->toDateString(),
            'reference'       => $run->run_number,
            'description'     => "CO Reconciliation — {$run->source_type} run #{$run->source_id}",
            'source_type'     => 'co_reconciliation',
            'source_id'       => $run->id,
            'currency_code'   => $posting->currency ?? 'SAR',
            'exchange_rate'   => 1,
            'total_debit'     => $amount,
            'total_credit'    => $amount,
            'status'          => JournalEntry::STATUS_POSTED,
            'posted_at'       => now(),
            'posted_by'       => $postedBy->id,
        ]);

        $je->lines()->createMany([
            [
                'account_id'      => $debitAccount->id,
                'description'     => 'CO reconciliation debit',
                'debit'           => $amount,
                'credit'          => 0,
                'cost_center_id'  => $posting->sender_cost_center_id,
                'currency_code'   => $posting->currency ?? 'SAR',
                'amount_currency' => $amount,
            ],
            [
                'account_id'      => $creditAccount->id,
                'description'     => 'CO reconciliation credit',
                'debit'           => 0,
                'credit'          => $amount,
                'cost_center_id'  => $posting->receiver_cost_center_id,
                'currency_code'   => $posting->currency ?? 'SAR',
                'amount_currency' => $amount,
            ],
        ]);

        return $je;
    }

    private function generateRunNumber(int $organizationId): string
    {
        $count = CoReconciliationRun::where('organization_id', $organizationId)->count() + 1;
        return 'KALC-' . date('Y') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
