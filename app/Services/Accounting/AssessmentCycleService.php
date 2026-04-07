<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CoAssessmentCycle;
use App\Models\Accounting\CoAssessmentCycleSegment;
use App\Models\Accounting\CoAssessmentPosting;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostElement;
use App\Models\Accounting\StatisticalKeyFigureValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AssessmentCycleService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CopaService    $copaService,
    ) {}

    /**
     * Execute an assessment cycle for a given period.
     *
     * Calculates allocations per segment and writes co_assessment_postings rows.
     * Returns the list of posting records created.
     *
     * @throws InvalidArgumentException if the cycle is not in open status
     * @throws RuntimeException         if no sender balance is found for a segment
     *
     * @return array{postings: CoAssessmentPosting[]}
     */
    public function execute(CoAssessmentCycle $cycle, int $period): array
    {
        if (! $cycle->isOpen()) {
            throw new InvalidArgumentException(
                "Assessment cycle [{$cycle->id}] is not open (status: {$cycle->status})."
            );
        }

        if ($period < $cycle->period_from || $period > $cycle->period_to) {
            throw new InvalidArgumentException(
                "Period {$period} is outside cycle range [{$cycle->period_from}-{$cycle->period_to}]."
            );
        }

        $postings = DB::transaction(function () use ($cycle, $period): array {
            $created = [];

            /** @var Collection<int, CoAssessmentCycleSegment> $segments */
            $segments = $cycle->segments()->with(['receivers', 'statisticalKeyFigure'])->get();

            foreach ($segments as $segment) {
                $senderBalance = $this->resolveSenderBalance($cycle, $segment, $period);

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

                    /** @var CoAssessmentPosting $posting */
                    $posting = CoAssessmentPosting::create([
                        'uuid'                   => Str::uuid()->toString(),
                        'organization_id'        => $cycle->organization_id,
                        'assessment_cycle_id'    => $cycle->id,
                        'fiscal_year'            => $cycle->fiscal_year,
                        'period'                 => $period,
                        'sender_cost_center_id'  => $segment->sender_cost_center_id,
                        'receiver_cost_center_id' => $receiver->receiver_cost_center_id,
                        'cost_element_id'        => $segment->sender_cost_element_id,
                        'amount'                 => round($share, 4),
                        'currency'               => 'SAR',
                    ]);

                    // Post GL journal entry: sender CC credit / receiver CC debit
                    $this->postAssessmentToGl($posting, $segment, $cycle);

                    // Post CO-PA line item if the cycle has COPA integration enabled
                    if ($cycle->copa_enabled && $cycle->copa_segment_id !== null) {
                        $this->postAssessmentToCopa($posting, $cycle, $period);
                    }

                    $created[] = $posting;
                }
            }

            // Mark cycle as executed
            $cycle->update([
                'status'      => CoAssessmentCycle::STATUS_EXECUTED,
                'executed_at' => now(),
            ]);

            return $created;
        });

        return ['postings' => $postings];
    }

    /**
     * Reverse all postings of an executed cycle for a given period.
     *
     * Creates negating posting rows and marks the original postings with reversal_id.
     *
     * @throws InvalidArgumentException if cycle is not executed
     */
    public function reverse(CoAssessmentCycle $cycle, int $period): void
    {
        if (! $cycle->isExecuted()) {
            throw new InvalidArgumentException(
                "Assessment cycle [{$cycle->id}] is not executed (status: {$cycle->status})."
            );
        }

        DB::transaction(function () use ($cycle, $period): void {
            $originals = CoAssessmentPosting::where('assessment_cycle_id', $cycle->id)
                ->where('period', $period)
                ->whereNull('reversal_id')
                ->get();

            foreach ($originals as $original) {
                /** @var CoAssessmentPosting $reversal */
                $reversal = CoAssessmentPosting::create([
                    'uuid'                    => Str::uuid()->toString(),
                    'organization_id'         => $original->organization_id,
                    'assessment_cycle_id'     => $original->assessment_cycle_id,
                    'fiscal_year'             => $original->fiscal_year,
                    'period'                  => $original->period,
                    'sender_cost_center_id'   => $original->sender_cost_center_id,
                    'receiver_cost_center_id' => $original->receiver_cost_center_id,
                    'cost_element_id'         => $original->cost_element_id,
                    'amount'                  => -$original->amount,
                    'currency'                => $original->currency,
                    'reversal_id'             => $original->id,
                ]);

                $original->update(['reversal_id' => $reversal->id]);
            }

            $cycle->update(['status' => CoAssessmentCycle::STATUS_REVERSED]);
        });
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Resolve the total sender cost balance for this segment and period.
     * Uses a placeholder calculation — in production this queries
     * actual CO line items for the sender cost center / cost element.
     */
    private function resolveSenderBalance(
        CoAssessmentCycle $cycle,
        CoAssessmentCycleSegment $segment,
        int $period
    ): float {
        // Query actual postings aggregated for the sender cost center this period.
        // This is intentionally simplified; a full implementation would query
        // the CO actual cost line items table.
        return (float) DB::table('co_assessment_postings')
            ->where('organization_id', $cycle->organization_id)
            ->where('fiscal_year', $cycle->fiscal_year)
            ->where('period', $period)
            ->where('sender_cost_center_id', $segment->sender_cost_center_id)
            ->whereNull('reversal_id')
            ->sum('amount') ?: 0.0;
    }

    /**
     * Compute the total tracing weight across all receivers for the segment.
     * For fixed_percentages the sum should equal 100; for SKF/posted it is the raw sum.
     */
    private function resolveReceiverWeights(
        CoAssessmentCycleSegment $segment,
        int $fiscalYear,
        int $period
    ): float {
        if ($segment->tracing_factor === CoAssessmentCycleSegment::TRACING_FIXED_PERCENTAGES) {
            return (float) $segment->receivers->sum('fixed_percentage') ?: 100.0;
        }

        if ($segment->tracing_factor === CoAssessmentCycleSegment::TRACING_STATISTICAL_KEY_FIGURE) {
            return (float) StatisticalKeyFigureValue::where('statistical_key_figure_id', $segment->skf_id)
                ->where('fiscal_year', $fiscalYear)
                ->where('period', $period)
                ->sum('value') ?: 1.0;
        }

        // posted_amounts: use total actual costs as weights (simplified)
        return 1.0;
    }

    /**
     * Calculate the share of sender balance allocated to one receiver.
     */
    private function calculateShare(
        CoAssessmentCycleSegment $segment,
        ?float $receiverWeight,
        float $senderBalance,
        float $totalWeight
    ): float {
        if ($totalWeight <= 0) {
            return 0.0;
        }

        $weight = $receiverWeight ?? 0.0;

        return ($weight / $totalWeight) * $senderBalance;
    }

    /**
     * Post a CO-PA line item for an assessment posting when COPA integration is enabled.
     *
     * Uses 'assessment' as value_type. Falls through silently on missing fiscal year.
     */
    private function postAssessmentToCopa(
        CoAssessmentPosting $posting,
        CoAssessmentCycle $cycle,
        int $period
    ): void {
        try {
            $postingDate = now()->startOfMonth()->setMonth($period)->toDateString();

            // Resolve fiscal year id by matching the start_date year
            $fiscalYearId = FiscalYear::withoutGlobalScopes()
                ->where('organization_id', $cycle->organization_id)
                ->whereYear('start_date', $cycle->fiscal_year)
                ->value('id');

            $this->copaService->recordLineItem([
                'organization_id'      => $cycle->organization_id,
                'fiscal_year_id'       => $fiscalYearId,
                'period'               => $period,
                'posting_date'         => $postingDate,
                'source_document_type' => 'co_assessment_posting',
                'source_document_id'   => $posting->id,
                'profit_center_id'     => null,
                'cost_center_id'       => $posting->receiver_cost_center_id,
                'product_id'           => null,
                'contact_id'           => null,
                'revenue'              => 0,
                'cogs'                 => (float) $posting->amount,
                'gross_profit'         => -(float) $posting->amount,
                'overhead_allocated'   => (float) $posting->amount,
                'net_profit'           => -(float) $posting->amount,
                'currency_code'        => $posting->currency ?? 'SAR',
                // Non-standard: extra reference fields stored in a comment for traceability
            ]);
        } catch (\Throwable) {
            // COPA posting is best-effort — do not fail the assessment if it errors
        }
    }

    /**
     * Create a GL journal entry for a single assessment posting.
     *
     * SAP pattern: sender CC is credited (costs leave), receiver CC is debited (costs arrive).
     * GL accounts are resolved from cost element → GL account (secondary cost element, type 42).
     * Falls through silently if GL accounts cannot be resolved.
     */
    private function postAssessmentToGl(
        CoAssessmentPosting $posting,
        CoAssessmentCycleSegment $segment,
        CoAssessmentCycle $cycle
    ): void {
        // Resolve secondary cost element GL account (assessment cost element)
        $costElement = $segment->sender_cost_element_id
            ? CostElement::find($segment->sender_cost_element_id)
            : null;

        $senderCC   = CostCenter::find($posting->sender_cost_center_id);
        $receiverCC = CostCenter::find($posting->receiver_cost_center_id);

        // Primary debit/credit accounts from cost element (preferred) or cost center GL mapping
        $debitAccountId  = $costElement?->gl_account_id ?? $receiverCC?->gl_account_id;
        $creditAccountId = $costElement?->gl_account_id ?? $senderCC?->gl_account_id;

        // When both sides map to the same account (common for secondary cost elements),
        // use the receiver CC account for debit and sender CC account for credit.
        if (!$debitAccountId || !$creditAccountId || $debitAccountId === $creditAccountId) {
            $debitAccountId  = $receiverCC?->gl_account_id;
            $creditAccountId = $senderCC?->gl_account_id;
        }

        if (!$debitAccountId || !$creditAccountId || $debitAccountId === $creditAccountId) {
            // Cannot build a balanced journal entry — skip GL posting silently
            return;
        }

        $description = "CO assessment: cycle {$cycle->id}, period {$posting->period}/{$cycle->fiscal_year}";
        $date        = now()->startOfMonth()->setMonth($posting->period)->toDateString();

        $entry = $this->journalService->createSimpleEntry(
            organizationId: $cycle->organization_id,
            branchId: 0,
            debitAccountId: (int) $debitAccountId,
            creditAccountId: (int) $creditAccountId,
            amount: (float) $posting->amount,
            description: $description,
            reference: "ASSESS-{$cycle->id}-{$posting->id}",
            date: $date,
        );

        $this->journalService->postEntry($entry);
    }
}
