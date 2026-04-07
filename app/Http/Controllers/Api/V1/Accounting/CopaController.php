<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CopaPlanVersion;
use App\Services\Accounting\CopaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopaController extends Controller
{
    public function __construct(
        private readonly CopaService $service
    ) {}

    /**
     * Aggregated profitability report grouped by product/customer/profit center.
     *
     * GET /copa/profitability
     *
     * Query params:
     *   fiscal_year_id, period, from_date, to_date,
     *   profit_center_id, cost_center_id, product_id, contact_id
     */
    public function profitability(Request $request): JsonResponse
    {
        $request->validate([
            'fiscal_year_id'  => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'period'          => ['nullable', 'integer', 'min:1', 'max:12'],
            'from_date'       => ['nullable', 'date'],
            'to_date'         => ['nullable', 'date', 'after_or_equal:from_date'],
            'profit_center_id' => ['nullable', 'integer', 'exists:profit_centers,id'],
            'cost_center_id'  => ['nullable', 'integer', 'exists:cost_centers,id'],
            'product_id'      => ['nullable', 'integer'],
            'contact_id'      => ['nullable', 'integer'],
        ]);

        $orgId   = $this->organizationId($request);
        $filters = array_merge(
            $request->only(['fiscal_year_id', 'period', 'from_date', 'to_date', 'profit_center_id', 'cost_center_id', 'product_id', 'contact_id']),
            ['organization_id' => $orgId]
        );

        $result = $this->service->getProfitabilityReport($filters);

        return $this->success($result);
    }

    /**
     * CO-PA breakdown by a single dimension type.
     *
     * GET /copa/dimension/{dimension}
     *
     * Path param: dimension — product_id | contact_id | profit_center_id | cost_center_id
     *
     * Query params: same as profitability
     */
    public function dimensionBreakdown(Request $request, string $dimension): JsonResponse
    {
        $request->validate([
            'fiscal_year_id'  => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'period'          => ['nullable', 'integer', 'min:1', 'max:12'],
            'from_date'       => ['nullable', 'date'],
            'to_date'         => ['nullable', 'date', 'after_or_equal:from_date'],
            'profit_center_id' => ['nullable', 'integer', 'exists:profit_centers,id'],
            'cost_center_id'  => ['nullable', 'integer', 'exists:cost_centers,id'],
            'product_id'      => ['nullable', 'integer'],
            'contact_id'      => ['nullable', 'integer'],
        ]);

        $orgId   = $this->organizationId($request);
        $filters = array_merge(
            $request->only(['fiscal_year_id', 'period', 'from_date', 'to_date', 'profit_center_id', 'cost_center_id', 'product_id', 'contact_id']),
            ['organization_id' => $orgId]
        );

        $result = $this->service->getDimensionBreakdown($dimension, $filters);

        return $this->success($result);
    }

    // ----------------------------------------------------------------
    // Plan Versions — Gap 2
    // ----------------------------------------------------------------

    /**
     * GET /copa/plan-versions
     * List all plan versions for the authenticated organisation.
     */
    public function planVersions(Request $request): JsonResponse
    {
        $request->validate([
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $query = CopaPlanVersion::where('organization_id', $this->organizationId($request))
            ->when($request->filled('fiscal_year_id'), fn($q) => $q->where('fiscal_year_id', $request->fiscal_year_id));

        return $this->success($query->orderByDesc('id')->paginate(25));
    }

    /**
     * POST /copa/plan-versions
     * Create a new plan version.
     */
    public function storePlanVersion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_name'   => ['required', 'string', 'max:100'],
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'is_active'      => ['boolean'],
        ]);

        $data['organization_id'] = $this->organizationId($request);

        $version = $this->service->createPlanVersion($data);

        return $this->success($version, 'CO-PA plan version created.', 201);
    }

    /**
     * POST /copa/plan-versions/{version}/items
     * Bulk upsert planned line items under a plan version.
     */
    public function storePlanItems(Request $request, CopaPlanVersion $version): JsonResponse
    {
        $request->validate([
            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.period'               => ['required', 'integer', 'min:1', 'max:12'],
            'lines.*.profit_center_id'     => ['nullable', 'integer', 'exists:profit_centers,id'],
            'lines.*.product_id'           => ['nullable', 'integer'],
            'lines.*.contact_id'           => ['nullable', 'integer'],
            'lines.*.planned_revenue'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.planned_cogs'         => ['nullable', 'numeric', 'min:0'],
            'lines.*.planned_gross_profit' => ['nullable', 'numeric'],
            'lines.*.planned_overhead'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.planned_net_profit'   => ['nullable', 'numeric'],
            'lines.*.currency_code'        => ['nullable', 'string', 'size:3'],
        ]);

        $this->service->bulkStorePlan($version, $request->input('lines'));

        return $this->success(null, 'Plan items stored successfully.');
    }

    /**
     * GET /copa/variance
     * Actual vs plan variance report.
     */
    public function varianceReport(Request $request): JsonResponse
    {
        $request->validate([
            'fiscal_year_id'   => ['required', 'integer', 'exists:fiscal_years,id'],
            'plan_version_id'  => ['required', 'integer', 'exists:copa_plan_versions,id'],
            'period'           => ['nullable', 'integer', 'min:1', 'max:12'],
            'profit_center_id' => ['nullable', 'integer', 'exists:profit_centers,id'],
            'product_id'       => ['nullable', 'integer'],
            'contact_id'       => ['nullable', 'integer'],
        ]);

        $orgId         = $this->organizationId($request);
        $fiscalYearId  = (int) $request->fiscal_year_id;
        $planVersionId = (int) $request->plan_version_id;
        $filters       = $request->only(['period', 'profit_center_id', 'product_id', 'contact_id']);

        $result = $this->service->getVarianceReport($orgId, $fiscalYearId, $planVersionId, $filters);

        return $this->success($result);
    }
}
