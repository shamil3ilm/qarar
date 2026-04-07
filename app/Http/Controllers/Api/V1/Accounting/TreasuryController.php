<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\LiquidityPlan;
use App\Models\Accounting\TreasuryInvestment;
use App\Services\Accounting\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(
        private TreasuryService $treasuryService,
    ) {}

    // =========================================================================
    // Investments
    // =========================================================================

    /**
     * GET /treasury/investments
     */
    public function index(Request $request): JsonResponse
    {
        $query = TreasuryInvestment::where('organization_id', $request->user()->organization_id)
            ->orderByDesc('investment_date')
            ->when($request->has('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->has('instrument_type'), fn($q) => $q->where('instrument_type', $request->string('instrument_type')));

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * POST /treasury/investments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'instrument_number' => ['nullable', 'string', 'max:30'],
            'instrument_type'   => ['required', 'in:fixed_deposit,money_market,bond,treasury_bill,mutual_fund'],
            'counterparty'      => ['required', 'string', 'max:150'],
            'principal_amount'  => ['required', 'numeric', 'min:0.01'],
            'interest_rate'     => ['required', 'numeric', 'min:0'],
            'investment_date'   => ['required', 'date'],
            'maturity_date'     => ['required', 'date', 'after:investment_date'],
            'currency_code'     => ['required', 'string', 'size:3'],
            'bank_account_id'   => ['nullable', 'exists:bank_accounts,id'],
            'gl_account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $investment = $this->treasuryService->createInvestment(array_merge($validated, [
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
        ]));

        return $this->created($investment);
    }

    /**
     * GET /treasury/investments/{treasuryInvestment}
     */
    public function show(TreasuryInvestment $treasuryInvestment): JsonResponse
    {
        return $this->success($treasuryInvestment->load(['bankAccount', 'glAccount']));
    }

    /**
     * POST /treasury/investments/{treasuryInvestment}/accrue
     */
    public function accrueInterest(Request $request, TreasuryInvestment $treasuryInvestment): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => ['required', 'date'],
        ]);

        $accrued = $this->treasuryService->accrueInterest($treasuryInvestment, $validated['as_of_date']);

        return $this->success([
            'accrued_interest' => $accrued,
            'investment'       => $treasuryInvestment->fresh(),
        ], 'Interest accrued successfully.');
    }

    /**
     * POST /treasury/investments/{treasuryInvestment}/mature
     */
    public function mature(TreasuryInvestment $treasuryInvestment): JsonResponse
    {
        $this->treasuryService->mature($treasuryInvestment);

        return $this->success($treasuryInvestment->fresh(), 'Investment matured successfully.');
    }

    /**
     * POST /treasury/investments/{treasuryInvestment}/pre-liquidate
     */
    public function preLiquidate(Request $request, TreasuryInvestment $treasuryInvestment): JsonResponse
    {
        $validated = $request->validate([
            'liquidation_date'        => ['required', 'date'],
            'early_redemption_penalty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->treasuryService->preLiquidate(
            $treasuryInvestment,
            $validated['liquidation_date'],
            (float) ($validated['early_redemption_penalty'] ?? 0),
        );

        return $this->success($treasuryInvestment->fresh(), 'Investment pre-liquidated successfully.');
    }

    // =========================================================================
    // Bank Positions
    // =========================================================================

    /**
     * GET /treasury/bank-positions
     */
    public function bankPositions(Request $request): JsonResponse
    {
        $date      = $request->string('date', now()->toDateString());
        $positions = $this->treasuryService->calculateBankPosition(
            $request->user()->organization_id,
            $date,
        );

        return $this->success($positions);
    }

    // =========================================================================
    // Liquidity Plans
    // =========================================================================

    /**
     * GET /treasury/liquidity-plans
     */
    public function liquidityPlans(Request $request): JsonResponse
    {
        $plans = LiquidityPlan::where('organization_id', $request->user()->organization_id)
            ->with('lines')
            ->orderByDesc('plan_from')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($plans);
    }

    /**
     * POST /treasury/liquidity-plans
     */
    public function createLiquidityPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_name'   => ['required', 'string', 'max:100'],
            'plan_from'   => ['required', 'date'],
            'plan_to'     => ['required', 'date', 'after_or_equal:plan_from'],
            'granularity' => ['required', 'in:daily,weekly,monthly'],
            'lines'       => ['nullable', 'array'],
            'lines.*.period_date'    => ['required', 'date'],
            'lines.*.category'       => ['required', 'string', 'max:100'],
            'lines.*.flow_type'      => ['required', 'in:inflow,outflow'],
            'lines.*.planned_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.currency_code'  => ['nullable', 'string', 'size:3'],
            'lines.*.bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        $plan = $this->treasuryService->createLiquidityPlan(array_merge($validated, [
            'organization_id' => $request->user()->organization_id,
        ]));

        return $this->created($plan);
    }

    /**
     * GET /treasury/liquidity-plans/{liquidityPlan}
     */
    public function showLiquidityPlan(LiquidityPlan $liquidityPlan): JsonResponse
    {
        return $this->success($liquidityPlan->load('lines'));
    }

    /**
     * POST /treasury/liquidity-plans/{liquidityPlan}/update-actuals
     */
    public function updateActuals(LiquidityPlan $liquidityPlan): JsonResponse
    {
        $liquidityPlan->load('lines');
        $this->treasuryService->updateActuals($liquidityPlan);

        return $this->success($liquidityPlan->fresh('lines'), 'Actuals updated successfully.');
    }

    // =========================================================================
    // Summaries
    // =========================================================================

    /**
     * GET /treasury/position-summary
     */
    public function positionSummary(Request $request): JsonResponse
    {
        $summary = $this->treasuryService->getPositionSummary($request->user()->organization_id);

        return $this->success($summary);
    }

    /**
     * GET /treasury/maturing-investments
     */
    public function maturingInvestments(Request $request): JsonResponse
    {
        $daysAhead   = $request->integer('days_ahead', 30);
        $investments = $this->treasuryService->getMaturingInvestments(
            $request->user()->organization_id,
            $daysAhead,
        );

        return $this->success($investments);
    }
}
