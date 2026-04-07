<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\PerformanceObligation;
use App\Models\Sales\RevenueContract;
use App\Models\Sales\RevenueRecognitionEvent;
use App\Services\Accounting\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevenueRecognitionService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Create a revenue contract with its performance obligations.
     * Automatically allocates the transaction price pro-rata across obligations.
     *
     * @param array<string, mixed>              $data        Contract header fields
     * @param array<int, array<string, mixed>>  $obligations Array of obligation data
     */
    public function createContract(array $data, array $obligations, int $userId): RevenueContract
    {
        if (empty($obligations)) {
            throw new InvalidArgumentException('A revenue contract must have at least one performance obligation.');
        }

        return DB::transaction(function () use ($data, $obligations, $userId): RevenueContract {
            $contract = RevenueContract::create($data);

            foreach ($obligations as $obligationData) {
                $obligationData['revenue_contract_id'] = $contract->id;
                $obligationData['status'] = $obligationData['status'] ?? PerformanceObligation::STATUS_PENDING;
                PerformanceObligation::create($obligationData);
            }

            // Allocate transaction price across obligations using SSP ratios
            $this->allocateTransactionPrice($contract->fresh('performanceObligations'));

            return $contract->fresh('performanceObligations');
        });
    }

    /**
     * Allocate total transaction price to obligations pro-rata by standalone selling price.
     * IFRS 15 para. 73 — relative standalone selling price method.
     */
    public function allocateTransactionPrice(RevenueContract $contract): void
    {
        $obligations = $contract->performanceObligations;

        if ($obligations->isEmpty()) {
            return;
        }

        $totalSsp = $obligations->sum(fn($o) => (float) $o->standalone_selling_price);

        if ($totalSsp <= 0) {
            throw new InvalidArgumentException('Total standalone selling price must be greater than zero for allocation.');
        }

        $totalPrice = (float) $contract->total_transaction_price;
        $allocated  = 0.0;

        DB::transaction(function () use ($obligations, $totalSsp, $totalPrice, &$allocated, $contract): void {
            foreach ($obligations as $index => $obligation) {
                $isLast = $index === $obligations->count() - 1;
                $ssp    = (float) $obligation->standalone_selling_price;
                $ratio  = $ssp / $totalSsp;

                // Assign remainder to the last obligation to avoid rounding drift
                $share = $isLast
                    ? round($totalPrice - $allocated, 4)
                    : round($totalPrice * $ratio, 4);

                $obligation->update([
                    'allocated_transaction_price' => $share,
                    'deferred_amount'             => $share,
                ]);

                $allocated += $share;
            }

            // Update contract allocated price
            $contract->update(['allocated_price' => $totalPrice]);
        });
    }

    /**
     * Recognize a specific amount for a performance obligation, creating a journal entry.
     */
    public function recognizeRevenue(
        PerformanceObligation $obligation,
        float $amount,
        Carbon $date,
        int $userId
    ): RevenueRecognitionEvent {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Recognition amount must be positive.');
        }

        $remaining = $obligation->getRemainingAmount();

        if ($amount > $remaining + 0.0001) {
            throw new InvalidArgumentException(
                "Cannot recognize {$amount}; only {$remaining} remains on this obligation."
            );
        }

        if ($obligation->isCompleted()) {
            throw new InvalidArgumentException('Performance obligation is already fully recognized.');
        }

        return DB::transaction(function () use ($obligation, $amount, $date, $userId): RevenueRecognitionEvent {
            $journalEntry = null;

            if ($obligation->revenue_account_id && $obligation->deferred_account_id) {
                $journalEntry = $this->journalService->create(
                    [
                        'organization_id' => $obligation->contract->organization_id,
                        'entry_date'      => $date->toDateString(),
                        'reference'       => "REV-REC-POB-{$obligation->id}",
                        'description'     => "Revenue recognition — {$obligation->description}",
                        'source_type'     => PerformanceObligation::class,
                        'source_id'       => $obligation->id,
                        'created_by'      => $userId,
                    ],
                    [
                        [
                            'account_id'  => $obligation->deferred_account_id,
                            'debit'       => $amount,
                            'credit'      => 0,
                            'description' => "Deferred revenue recognised — {$obligation->description}",
                        ],
                        [
                            'account_id'  => $obligation->revenue_account_id,
                            'debit'       => 0,
                            'credit'      => $amount,
                            'description' => "Revenue recognised — {$obligation->description}",
                        ],
                    ]
                );
            }

            $event = RevenueRecognitionEvent::create([
                'performance_obligation_id' => $obligation->id,
                'event_date'                => $date->toDateString(),
                'amount_recognized'         => $amount,
                'journal_entry_id'          => $journalEntry?->id,
                'created_by'                => $userId,
            ]);

            $newRecognized = (float) $obligation->recognized_amount + $amount;
            $newDeferred   = max(0, (float) $obligation->deferred_amount - $amount);
            $isFullyRecognized = bccomp(
                (string) $newRecognized,
                (string) $obligation->allocated_transaction_price,
                4
            ) >= 0;

            $obligation->update([
                'recognized_amount' => $newRecognized,
                'deferred_amount'   => $newDeferred,
                'status'            => $isFullyRecognized
                    ? PerformanceObligation::STATUS_COMPLETED
                    : PerformanceObligation::STATUS_IN_PROGRESS,
            ]);

            return $event;
        });
    }

    /**
     * Recognize the full remaining amount at a point in time.
     * Used for point-in-time obligations upon delivery/completion.
     */
    public function recognizeAtPointInTime(PerformanceObligation $obligation, int $userId): RevenueRecognitionEvent
    {
        if ($obligation->recognition_method !== PerformanceObligation::METHOD_POINT_IN_TIME) {
            throw new InvalidArgumentException(
                'This method is only applicable to point-in-time performance obligations.'
            );
        }

        $amount = $obligation->getRemainingAmount();

        if ($amount <= 0) {
            throw new InvalidArgumentException('There is no remaining amount to recognize.');
        }

        return $this->recognizeRevenue($obligation, $amount, Carbon::today(), $userId);
    }

    /**
     * Recognize revenue proportional to the completion percentage.
     * Used for over-time obligations (e.g. service contracts).
     *
     * @param float $completionPct 0–100
     */
    public function recognizeProgressBased(
        PerformanceObligation $obligation,
        float $completionPct,
        int $userId
    ): RevenueRecognitionEvent {
        if ($obligation->recognition_method === PerformanceObligation::METHOD_POINT_IN_TIME) {
            throw new InvalidArgumentException(
                'Progress-based recognition is not applicable to point-in-time obligations.'
            );
        }

        if ($completionPct < 0 || $completionPct > 100) {
            throw new InvalidArgumentException('Completion percentage must be between 0 and 100.');
        }

        $totalAllocated   = (float) $obligation->allocated_transaction_price;
        $totalRecognized  = (float) $obligation->recognized_amount;
        $targetRecognized = round($totalAllocated * ($completionPct / 100), 4);
        $increment        = round($targetRecognized - $totalRecognized, 4);

        if ($increment <= 0) {
            throw new InvalidArgumentException(
                "Completion at {$completionPct}% yields no additional recognition (already at or above that level)."
            );
        }

        $obligation->update(['completion_percentage' => $completionPct]);

        return $this->recognizeRevenue($obligation, $increment, Carbon::today(), $userId);
    }

    /**
     * Return an aggregate of deferred revenue balances per performance obligation
     * for a given organization.
     *
     * @return array<int, array{obligation_id: int, description: string, deferred_amount: float, contract_number: string}>
     */
    public function getDeferredRevenueBalance(int $organizationId): array
    {
        return PerformanceObligation::whereHas(
            'contract',
            fn($q) => $q->where('organization_id', $organizationId)
                ->whereIn('status', [RevenueContract::STATUS_ACTIVE])
        )
            ->where('deferred_amount', '>', 0)
            ->with('contract:id,contract_number')
            ->get()
            ->map(fn(PerformanceObligation $o) => [
                'obligation_id'   => $o->id,
                'description'     => $o->description,
                'deferred_amount' => (float) $o->deferred_amount,
                'contract_number' => $o->contract?->contract_number ?? '',
            ])
            ->all();
    }
}
