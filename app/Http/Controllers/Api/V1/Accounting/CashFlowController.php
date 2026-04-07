<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowScenario;
use App\Services\Accounting\CashFlowForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    public function __construct(
        private readonly CashFlowForecastService $forecastService
    ) {}

    // -------------------------------------------------------------------------
    // Forecasts
    // -------------------------------------------------------------------------

    public function generateForecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'horizon_days'  => 'nullable|integer|in:30,60,90',
            'currency_code' => 'nullable|string|size:3',
            'scenario_id'   => 'nullable|exists:cash_flow_scenarios,id',
        ]);

        $organization = $this->organization($request);
        $scenario     = isset($validated['scenario_id'])
            ? CashFlowScenario::findOrFail($validated['scenario_id'])
            : null;

        $forecast = $this->forecastService->generateForecast(
            $organization,
            (int) ($validated['horizon_days'] ?? 90),
            $scenario,
            $validated['currency_code'] ?? 'SAR'
        );

        $summary = $this->forecastService->getPeriodSummary($forecast);

        return $this->success([
            'forecast' => $forecast->load(['scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast generated.', 201);
    }

    public function showForecast(Request $request, CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $summary = $this->forecastService->getPeriodSummary($cashFlowForecast);

        return $this->success([
            'forecast'       => $cashFlowForecast->load(['lines', 'scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast retrieved.');
    }

    public function indexForecasts(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $forecasts = CashFlowForecast::where('organization_id', $organizationId)
            ->with(['scenario'])
            ->when($request->input('scenario_id'), fn($q, $v) => $q->where('scenario_id', $v))
            ->orderByDesc('forecast_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($forecasts, null, 'Cash flow forecasts retrieved.');
    }

    public function refreshForecast(CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $forecast = $this->forecastService->refreshForecast($cashFlowForecast);
        $summary  = $this->forecastService->getPeriodSummary($forecast);

        return $this->success([
            'forecast'       => $forecast->load(['lines', 'scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast refreshed.');
    }

    public function forecastLines(Request $request, CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $lines = $cashFlowForecast->lines()
            ->when($request->input('flow_type'), fn($q, $v) => $q->where('flow_type', $v))
            ->when($request->input('confidence'), fn($q, $v) => $q->where('confidence', $v))
            ->when($request->input('source_type'), fn($q, $v) => $q->where('source_type', $v))
            ->orderBy('expected_date')
            ->paginate($request->integer('per_page', 30));

        return $this->paginated($lines, null, 'Cash flow lines retrieved.');
    }

    // -------------------------------------------------------------------------
    // Scenarios
    // -------------------------------------------------------------------------

    public function indexScenarios(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $scenarios = CashFlowScenario::where('organization_id', $organizationId)
            ->with(['creator'])
            ->orderByDesc('is_base_case')
            ->orderBy('name')
            ->get();

        return $this->success($scenarios, 'Cash flow scenarios retrieved.');
    }

    public function storeScenario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'is_base_case' => 'nullable|boolean',
            'assumptions'  => 'nullable|array',
        ]);

        $organizationId = $this->organizationId($request);

        // Ensure only one base case
        if (!empty($validated['is_base_case'])) {
            CashFlowScenario::where('organization_id', $organizationId)
                ->where('is_base_case', true)
                ->update(['is_base_case' => false]);
        }

        $scenario = CashFlowScenario::create(array_merge($validated, [
            'organization_id' => $organizationId,
            'created_by'      => auth()->id(),
        ]));

        return $this->success($scenario->load(['creator']), 'Cash flow scenario created.', 201);
    }

    public function updateScenario(Request $request, CashFlowScenario $cashFlowScenario): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'description'  => 'nullable|string',
            'is_base_case' => 'nullable|boolean',
            'assumptions'  => 'nullable|array',
        ]);

        if (!empty($validated['is_base_case'])) {
            CashFlowScenario::where('organization_id', $cashFlowScenario->organization_id)
                ->where('is_base_case', true)
                ->where('id', '!=', $cashFlowScenario->id)
                ->update(['is_base_case' => false]);
        }

        $cashFlowScenario->update($validated);

        return $this->success($cashFlowScenario->fresh()->load(['creator']), 'Cash flow scenario updated.');
    }

    public function destroyScenario(CashFlowScenario $cashFlowScenario): JsonResponse
    {
        $cashFlowScenario->delete();

        return $this->success(null, 'Cash flow scenario deleted.');
    }
}
