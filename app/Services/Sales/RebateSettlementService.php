<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\RebateAccrual;
use App\Models\Sales\RebateMaster;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebate Settlement — SAP SD BO01/VBo1 equivalent.
 *
 * At period-end (monthly/quarterly/annually), this service:
 *  1. Aggregates all open accruals per rebate master into a settlement amount.
 *  2. Creates a credit note or cash payment to the customer.
 *  3. Reverses the accrual GL entries and posts the settlement GL entry.
 *  4. Marks accruals as settled.
 */
class RebateSettlementService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Calculate the total outstanding accrual for a rebate master.
     */
    public function getOutstandingBalance(RebateMaster $rebate): array
    {
        $accruals = RebateAccrual::where('rebate_master_id', $rebate->id)
            ->where('status', RebateAccrual::STATUS_POSTED)
            ->get();

        return [
            'rebate_master_id' => $rebate->id,
            'rebate_name'      => $rebate->name,
            'accrual_count'    => $accruals->count(),
            'total_accrued'    => $accruals->sum('rebate_amount'),
            'currency'            => $rebate->customer?->currency ?? 'SAR',
            'accruals'            => $accruals,
        ];
    }

    /**
     * Settle all open accruals for a rebate master up to the given date.
     *
     * @return array{settlement_amount: float, accruals_settled: int, journal_entry_id: int|null}
     */
    public function settle(
        RebateMaster $rebate,
        Carbon $settlementDate,
        string $settlementMethod = 'credit_note', // credit_note | payment
        int $settledByUserId = 0,
    ): array {
        return DB::transaction(function () use ($rebate, $settlementDate, $settlementMethod, $settledByUserId): array {
            $accruals = RebateAccrual::where('rebate_master_id', $rebate->id)
                ->where('status', RebateAccrual::STATUS_POSTED)
                ->where('accrual_date', '<=', $settlementDate)
                ->lockForUpdate()
                ->get();

            if ($accruals->isEmpty()) {
                return [
                    'settlement_amount' => 0.0,
                    'accruals_settled'  => 0,
                    'journal_entry_id'  => null,
                ];
            }

            $totalAmount = (float) $accruals->sum('rebate_amount');
            $journalEntryId = null;

            // Post settlement journal entry if accounts are configured
            if ($rebate->accrual_account_id && $rebate->expense_account_id) {
                $journalEntry = $this->journalService->createJournalEntry(
                    organizationId: $rebate->organization_id,
                    description: "Rebate settlement: {$rebate->name} ({$settlementDate->format('Y-m')})",
                    lines: [
                        // Debit accrual account (clearing the liability/accrual)
                        [
                            'account_id' => $rebate->accrual_account_id,
                            'type'       => 'debit',
                            'amount'     => $totalAmount,
                        ],
                        // Credit expense / payable account (actual payment obligation)
                        [
                            'account_id' => $rebate->expense_account_id,
                            'type'       => 'credit',
                            'amount'     => $totalAmount,
                        ],
                    ],
                    date: $settlementDate,
                );
                $journalEntryId = $journalEntry->id ?? null;
            }

            // Mark accruals as settled
            $referenceNumber = 'RS-' . strtoupper($settlementMethod) . '-' . $settlementDate->format('Ymd') . '-' . $rebate->id;

            $accruals->each(function (RebateAccrual $accrual) use ($referenceNumber, $journalEntryId): void {
                $accrual->update([
                    'status'          => RebateAccrual::STATUS_SETTLED,
                    'settlement_ref'  => $referenceNumber,
                    'journal_entry_id' => $journalEntryId,
                    'settled_at'      => now(),
                ]);
            });

            return [
                'settlement_amount' => $totalAmount,
                'accruals_settled'  => $accruals->count(),
                'journal_entry_id'  => $journalEntryId,
                'reference_number'  => $referenceNumber,
                'settlement_method' => $settlementMethod,
            ];
        });
    }

    /**
     * Bulk settle — settle all active rebates for an organization in one period-end run.
     */
    public function periodEndRun(int $organizationId, Carbon $periodEnd): array
    {
        $rebates = RebateMaster::where('organization_id', $organizationId)
            ->where('status', RebateMaster::STATUS_ACTIVE)
            ->get();

        $results = [];

        foreach ($rebates as $rebate) {
            $result = $this->settle($rebate, $periodEnd);
            if ($result['accruals_settled'] > 0) {
                $results[] = array_merge(['rebate_id' => $rebate->id, 'rebate_name' => $rebate->name], $result);
            }
        }

        return [
            'period_end'       => $periodEnd->toDateString(),
            'rebates_processed' => count($results),
            'total_settled'    => array_sum(array_column($results, 'settlement_amount')),
            'details'          => $results,
        ];
    }
}
