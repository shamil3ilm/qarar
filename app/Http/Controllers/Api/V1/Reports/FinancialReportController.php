<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    public function __construct(
        private FinancialReportService $reportService
    ) {}

    /**
     * Get Profit & Loss statement.
     */
    public function profitAndLoss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->reportService->getProfitAndLoss(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return $this->success($report);
    }

    /**
     * Get Balance Sheet.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = isset($validated['as_of_date'])
            ? Carbon::parse($validated['as_of_date'])
            : now();

        $report = $this->reportService->getBalanceSheet($asOfDate);

        return $this->success($report);
    }

    /**
     * Get Cash Flow statement.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->reportService->getCashFlow(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date'])
        );

        return $this->success($report);
    }

    /**
     * Get Trial Balance.
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = isset($validated['as_of_date'])
            ? Carbon::parse($validated['as_of_date'])
            : now();

        $report = $this->reportService->getTrialBalance($asOfDate);

        return $this->success($report);
    }

    /**
     * Get Accounts Receivable Aging report.
     */
    public function receivableAging(): JsonResponse
    {
        $report = $this->reportService->getReceivableAging();

        return $this->success($report);
    }

    /**
     * Get Accounts Payable Aging report.
     */
    public function payableAging(): JsonResponse
    {
        $report = $this->reportService->getPayableAging();

        return $this->success($report);
    }

    /**
     * Get Actual vs Budget variance report.
     */
    public function actualVsBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'budget_type'  => 'nullable|in:annual,quarterly,project,department',
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
        ]);

        $report = $this->reportService->getActualVsBudget(array_filter($validated));

        return $this->success($report);
    }
}
