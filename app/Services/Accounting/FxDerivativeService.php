<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxHedgeRelation;
use App\Models\Accounting\FxValuation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FX Derivative & Hedge Accounting — SAP TRM / IFRS 9.
 *
 * Covers:
 *  - FX forward contract lifecycle (book, mature, cancel)
 *  - Hedge designation and de-designation
 *  - Period-end mark-to-market (MTM) valuation with fair-value/cash-flow hedge split
 *  - Gain/loss journal entry posting
 *
 * Fair-value hedge: full fair-value change through P&L.
 * Cash-flow hedge:  effective portion → OCI; ineffective portion → P&L.
 */
class FxDerivativeService
{
    public function __construct(private readonly JournalService $journalService) {}

    // ----------------------------------------------------------------
    // Forward lifecycle
    // ----------------------------------------------------------------

    public function bookForward(int $organizationId, array $data, int $createdBy): FxForward
    {
        $contractNumber = 'FWD-' . Carbon::now()->format('Y') . '-' . strtoupper(substr(uniqid(), -6));

        return FxForward::create([
            'organization_id'                 => $organizationId,
            'contract_number'                 => $contractNumber,
            'counterparty_bank'               => $data['counterparty_bank'] ?? null,
            'buy_currency'                    => strtoupper($data['buy_currency']),
            'sell_currency'                   => strtoupper($data['sell_currency']),
            'notional_amount'                 => $data['notional_amount'],
            'forward_rate'                    => $data['forward_rate'],
            'trade_date'                      => $data['trade_date'],
            'maturity_date'                   => $data['maturity_date'],
            'purpose'                         => $data['purpose'] ?? 'hedge',
            'status'                          => 'active',
            'derivative_asset_account_id'     => $data['derivative_asset_account_id'] ?? null,
            'unrealised_gain_loss_account_id' => $data['unrealised_gain_loss_account_id'] ?? null,
            'realised_gain_loss_account_id'   => $data['realised_gain_loss_account_id'] ?? null,
            'created_by'                      => $createdBy,
        ]);
    }

    public function designateHedge(FxForward $forward, array $data): FxHedgeRelation
    {
        return FxHedgeRelation::create([
            'organization_id'       => $forward->organization_id,
            'fx_forward_id'         => $forward->id,
            'hedge_type'            => $data['hedge_type'],         // fair_value | cash_flow
            'hedged_item_type'      => $data['hedged_item_type'],
            'hedged_item_id'        => $data['hedged_item_id'] ?? null,
            'hedged_item_description' => $data['hedged_item_description'] ?? null,
            'hedge_ratio'           => $data['hedge_ratio'] ?? 1.0,
            'designation_date'      => $data['designation_date'],
            'status'                => 'designated',
        ]);
    }

    public function dedesignateHedge(FxHedgeRelation $relation, string $dedesignationDate): FxHedgeRelation
    {
        $relation->update([
            'status'              => 'dedesignated',
            'dedesignation_date'  => $dedesignationDate,
        ]);

        return $relation;
    }

    // ----------------------------------------------------------------
    // Period-end MTM valuation
    // ----------------------------------------------------------------

    /**
     * Record a mark-to-market valuation for a forward at the given spot rate.
     *
     * Fair value = (spot_rate - forward_rate) × notional  [simplified linear MTM]
     *
     * For cash-flow hedges: splits into effective/ineffective portions.
     */
    public function recordValuation(
        FxForward $forward,
        Carbon $valuationDate,
        float $spotRate,
    ): FxValuation {
        return DB::transaction(function () use ($forward, $valuationDate, $spotRate): FxValuation {
            $fairValue = round(((float) $spotRate - (float) $forward->forward_rate) * (float) $forward->notional_amount, 4);

            $previousValuation = $forward->valuations()->latest('valuation_date')->first();
            $previousFairValue = $previousValuation ? (float) $previousValuation->fair_value : 0.0;
            $fairValueChange   = round($fairValue - $previousFairValue, 4);

            // Hedge effectiveness split (simplified: ratio × change = effective)
            $hedgeRelation     = $forward->hedgeRelation;
            $effectivePortion  = 0.0;
            $ineffectivePortion = $fairValueChange;

            if ($hedgeRelation && $hedgeRelation->hedge_type === 'cash_flow') {
                $effectivePortion   = round($fairValueChange * (float) $hedgeRelation->hedge_ratio, 4);
                $ineffectivePortion = round($fairValueChange - $effectivePortion, 4);
            }

            $valuation = FxValuation::create([
                'fx_forward_id'       => $forward->id,
                'valuation_date'      => $valuationDate,
                'spot_rate'           => $spotRate,
                'fair_value'          => $fairValue,
                'fair_value_change'   => $fairValueChange,
                'effective_portion'   => $effectivePortion,
                'ineffective_portion' => $ineffectivePortion,
            ]);

            // Post journal entry if accounts configured
            if ($forward->derivative_asset_account_id && $forward->unrealised_gain_loss_account_id && $fairValueChange !== 0.0) {
                $this->postValuationJournalEntry($forward, $valuation);
            }

            return $valuation;
        });
    }

    // ----------------------------------------------------------------
    // Settlement / maturity
    // ----------------------------------------------------------------

    /**
     * Settle a forward at maturity using the actual spot rate.
     */
    public function settle(FxForward $forward, float $settlementRate, Carbon $settlementDate): FxForward
    {
        return DB::transaction(function () use ($forward, $settlementRate, $settlementDate): FxForward {
            $gainLoss = round(($settlementRate - (float) $forward->forward_rate) * (float) $forward->notional_amount, 4);

            $forward->update([
                'status'               => 'exercised',
                'settlement_rate'      => $settlementRate,
                'settlement_gain_loss' => $gainLoss,
                'settled_at'           => $settlementDate,
            ]);

            // Post realised gain/loss entry if accounts configured
            if ($forward->realised_gain_loss_account_id && $gainLoss !== 0.0) {
                $this->postRealisedGainLoss($forward, $gainLoss, $settlementDate);
            }

            return $forward->fresh('valuations');
        });
    }

    // ----------------------------------------------------------------

    private function postValuationJournalEntry(FxForward $forward, FxValuation $valuation): void
    {
        $amount = abs((float) $valuation->fair_value_change);
        $isGain = (float) $valuation->fair_value_change >= 0;

        $this->journalService->createJournalEntry(
            organizationId: $forward->organization_id,
            description: "FX forward MTM: {$forward->contract_number} @ {$valuation->valuation_date}",
            lines: [
                [
                    'account_id' => $forward->derivative_asset_account_id,
                    'type'       => $isGain ? 'debit' : 'credit',
                    'amount'     => $amount,
                ],
                [
                    'account_id' => $forward->unrealised_gain_loss_account_id,
                    'type'       => $isGain ? 'credit' : 'debit',
                    'amount'     => $amount,
                ],
            ],
            date: $valuation->valuation_date,
        );
    }

    private function postRealisedGainLoss(FxForward $forward, float $gainLoss, Carbon $date): void
    {
        $amount = abs($gainLoss);
        $isGain = $gainLoss >= 0;

        $this->journalService->createJournalEntry(
            organizationId: $forward->organization_id,
            description: "FX forward settled: {$forward->contract_number} — realised " . ($isGain ? 'gain' : 'loss'),
            lines: [
                [
                    'account_id' => $forward->derivative_asset_account_id,
                    'type'       => $isGain ? 'credit' : 'debit',
                    'amount'     => $amount,
                ],
                [
                    'account_id' => $forward->realised_gain_loss_account_id,
                    'type'       => $isGain ? 'credit' : 'debit',
                    'amount'     => $amount,
                ],
            ],
            date: $date,
        );
    }
}
