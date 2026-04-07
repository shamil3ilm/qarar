<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CoDistributionCycle;
use App\Models\Accounting\CoDistributionPosting;
use App\Models\Accounting\CoDistributionSegment;
use App\Models\Accounting\StatisticalKeyFigureValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DistributionCycleService
{
    /**
     * Execute a distribution cycle for the given period.
     *
     * Unlike assessment cycles, distribution keeps the original primary cost elements
     * and redistributes primary costs directly from sender to receivers.
     *
     * @throws InvalidArgumentException if the cycle is not open or period is out of range
     *
     * @return array{postings: CoDistributionPosting[]}
     */
    public function execute(CoDistributionCycle $cycle, int $period): array
    {
        if (! $cycle->isOpen()) {
            throw new InvalidArgumentException(
                "Distribution cycle [{$cycle->id}] is not open (status: {$cycle->status})."
            );
        }

        if ($period < $cycle->period_from || $period > $cycle->period_to) {
            throw new InvalidArgumentException(
                "Period {$period} is outside cycle range [{$cycle->period_from}-{$cycle->period_to}]."
            );
        }

        $postings = DB::transaction(function () use ($cycle, $period): array {
            $created = [];

            /** @var Collection<int, CoDistributionSegment> $segments */
            $segments = $cycle->segments()->with(['receivers', 'statisticalKeyFigure'])->get();

            foreach ($segments as $segment) {
                $costElementIds = $segment->cost_element_ids;

                if (empty($costElementIds)) {
                    continue;
                }

                foreach ($costElementIds as $costElementId) {
                    $senderBalance = $this->resolveSenderBalance(
                        $cycle,
                        $segment,
                        (int) $costElementId,
                        $period
                    );

                    if ($senderBalance <= 0) {
                        continue;
                    }

                    $totalWeight = $this->resolveReceiverWeights($segment, $cycle->fiscal_year, $period);

                    foreach ($segment->receivers as $receiver) {
                        $share = $this->calculateShare(
                            $segment,
                            $receiver->fixed_percentage,
                            $senderBalance,
                            $totalWeight
                        );

                        if ($share <= 0) {
                            continue;
                        }

                        /** @var CoDistributionPosting $posting */
                        $posting = CoDistributionPosting::create([
                            'uuid'                    => Str::uuid()->toString(),
                            'organization_id'         => $cycle->organization_id,
                            'distribution_cycle_id'   => $cycle->id,
                            'fiscal_year'             => $cycle->fiscal_year,
                            'period'                  => $period,
                            'sender_cost_center_id'   => $segment->sender_cost_center_id,
                            'receiver_cost_center_id' => $receiver->receiver_cost_center_id,
                            'cost_element_id'         => $costElementId,
                            'amount'                  => round($share, 4),
                            'currency'                => 'SAR',
                        ]);

                        $created[] = $posting;
                    }
                }
            }

            $cycle->update([
                'status'      => CoDistributionCycle::STATUS_EXECUTED,
                'executed_at' => now(),
            ]);

            return $created;
        });

        return ['postings' => $postings];
    }

    /**
     * Reverse all postings for an executed distribution cycle in a given period.
     *
     * @throws InvalidArgumentException if the cycle is not executed
     */
    public function reverse(CoDistributionCycle $cycle, int $period): void
    {
        if (! $cycle->isExecuted()) {
            throw new InvalidArgumentException(
                "Distribution cycle [{$cycle->id}] is not executed (status: {$cycle->status})."
            );
        }

        DB::transaction(function () use ($cycle, $period): void {
            // Delete postings for the period (distribution postings have no reversal_id column,
            // so we simply delete them and reset cycle status to open)
            CoDistributionPosting::where('distribution_cycle_id', $cycle->id)
                ->where('period', $period)
                ->delete();

            $cycle->update(['status' => CoDistributionCycle::STATUS_REVERSED]);
        });
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function resolveSenderBalance(
        CoDistributionCycle $cycle,
        CoDistributionSegment $segment,
        int $costElementId,
        int $period
    ): float {
        // Aggregate existing distribution postings as a proxy for the sender balance.
        // A full implementation would query from the primary CO actual costs table.
        return (float) DB::table('co_distribution_postings')
            ->where('organization_id', $cycle->organization_id)
            ->where('fiscal_year', $cycle->fiscal_year)
            ->where('period', $period)
            ->where('sender_cost_center_id', $segment->sender_cost_center_id)
            ->where('cost_element_id', $costElementId)
            ->sum('amount') ?: 0.0;
    }

    private function resolveReceiverWeights(
        CoDistributionSegment $segment,
        int $fiscalYear,
        int $period
    ): float {
        if ($segment->tracing_factor === CoDistributionSegment::TRACING_FIXED_PERCENTAGES) {
            return (float) $segment->receivers->sum('fixed_percentage') ?: 100.0;
        }

        if ($segment->tracing_factor === CoDistributionSegment::TRACING_STATISTICAL_KEY_FIGURE) {
            return (float) StatisticalKeyFigureValue::where('statistical_key_figure_id', $segment->skf_id)
                ->where('fiscal_year', $fiscalYear)
                ->where('period', $period)
                ->sum('value') ?: 1.0;
        }

        return 1.0;
    }

    private function calculateShare(
        CoDistributionSegment $segment,
        ?float $receiverWeight,
        float $senderBalance,
        float $totalWeight
    ): float {
        if ($totalWeight <= 0) {
            return 0.0;
        }

        return (($receiverWeight ?? 0.0) / $totalWeight) * $senderBalance;
    }
}
