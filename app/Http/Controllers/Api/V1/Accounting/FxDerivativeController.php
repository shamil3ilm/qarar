<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxHedgeRelation;
use App\Services\Accounting\FxDerivativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FxDerivativeController extends Controller
{
    public function __construct(private readonly FxDerivativeService $service) {}

    /** GET /fx-forwards */
    public function index(Request $request): JsonResponse
    {
        $forwards = FxForward::where('organization_id', $request->user()->organization_id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->buy_currency, fn ($q) => $q->where('buy_currency', $request->buy_currency))
            ->with(['hedgeRelation', 'latestValuation'])
            ->orderByDesc('trade_date')
            ->paginate((int) $request->get('per_page', 20));

        return $this->paginated($forwards);
    }

    /** POST /fx-forwards */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counterparty_bank'               => ['nullable', 'string'],
            'buy_currency'                    => ['required', 'string', 'size:3'],
            'sell_currency'                   => ['required', 'string', 'size:3'],
            'notional_amount'                 => ['required', 'numeric', 'min:0.01'],
            'forward_rate'                    => ['required', 'numeric', 'min:0.000001'],
            'trade_date'                      => ['required', 'date'],
            'maturity_date'                   => ['required', 'date', 'after:trade_date'],
            'purpose'                         => ['in:speculative,hedge'],
            'derivative_asset_account_id'     => ['nullable', 'integer'],
            'unrealised_gain_loss_account_id' => ['nullable', 'integer'],
            'realised_gain_loss_account_id'   => ['nullable', 'integer'],
        ]);

        $forward = $this->service->bookForward(
            organizationId: $request->user()->organization_id,
            data:           $data,
            createdBy:      $request->user()->id,
        );

        return $this->success($forward, 'FX forward booked', 201);
    }

    /** GET /fx-forwards/{forward} */
    public function show(FxForward $fxForward): JsonResponse
    {
        return $this->success($fxForward->load(['hedgeRelation', 'valuations']));
    }

    /** POST /fx-forwards/{forward}/designate-hedge */
    public function designateHedge(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'hedge_type'              => ['required', 'in:fair_value,cash_flow,net_investment'],
            'hedged_item_type'        => ['required', 'string'],
            'hedged_item_id'          => ['nullable', 'integer'],
            'hedged_item_description' => ['nullable', 'string'],
            'hedge_ratio'             => ['numeric', 'min:0.01', 'max:1.0'],
            'designation_date'        => ['required', 'date'],
        ]);

        $relation = $this->service->designateHedge($fxForward, $data);

        return $this->success($relation, 'Hedge relationship designated', 201);
    }

    /** POST /fx-forwards/{forward}/dedesignate-hedge */
    public function dedesignateHedge(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'dedesignation_date' => ['required', 'date'],
        ]);

        $relation = FxHedgeRelation::where('fx_forward_id', $fxForward->id)
            ->where('status', 'designated')
            ->firstOrFail();

        $relation = $this->service->dedesignateHedge($relation, $data['dedesignation_date']);

        return $this->success($relation, 'Hedge relationship de-designated');
    }

    /** POST /fx-forwards/{forward}/valuate */
    public function valuate(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'valuation_date' => ['required', 'date'],
            'spot_rate'      => ['required', 'numeric', 'min:0.000001'],
        ]);

        $valuation = $this->service->recordValuation(
            forward:        $fxForward,
            valuationDate:  Carbon::parse($data['valuation_date']),
            spotRate:       (float) $data['spot_rate'],
        );

        return $this->success($valuation, 'MTM valuation recorded', 201);
    }

    /** POST /fx-forwards/{forward}/settle */
    public function settle(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'settlement_rate' => ['required', 'numeric', 'min:0.000001'],
            'settlement_date' => ['required', 'date'],
        ]);

        $forward = $this->service->settle(
            forward:         $fxForward,
            settlementRate:  (float) $data['settlement_rate'],
            settlementDate:  Carbon::parse($data['settlement_date']),
        );

        return $this->success($forward, 'FX forward settled');
    }
}
