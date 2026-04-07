<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\MaterialLedgerRecord;
use App\Services\Accounting\MaterialLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialLedgerController extends Controller
{
    public function __construct(
        private MaterialLedgerService $service
    ) {}

    /**
     * List material ledger records with optional period/year/warehouse filters.
     */
    public function index(Request $request): JsonResponse
    {
        $records = $this->service->index([
            ...$request->only(['period', 'fiscal_year', 'status', 'warehouse_id', 'per_page']),
        ]);

        return $this->paginated($records);
    }

    /**
     * List all material ledger records for a specific product.
     */
    public function show(Request $request, int $productId): JsonResponse
    {
        $records = $this->service->getProductRecords($productId, [
            ...$request->only(['fiscal_year', 'per_page']),
        ]);

        return $this->paginated($records);
    }

    /**
     * Run period close for the specified period and fiscal year.
     */
    public function runPeriodClose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $result = $this->service->runPeriodClose(
            (int) $validated['period'],
            (int) $validated['fiscal_year'],
            (int) $this->organizationId($request)
        );

        return $this->success($result, $result['message'] ?? 'Period close completed.');
    }

    /**
     * Get a summary report for a given period and fiscal year.
     */
    public function periodReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $report = $this->service->getPeriodReport(
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($report, 'Period report generated.');
    }

    /**
     * List closing entries with optional period/year filter.
     */
    public function closingEntries(Request $request): JsonResponse
    {
        $entries = $this->service->getClosingEntries([
            ...$request->only(['period', 'fiscal_year', 'per_page']),
        ]);

        return $this->paginated($entries);
    }
}
