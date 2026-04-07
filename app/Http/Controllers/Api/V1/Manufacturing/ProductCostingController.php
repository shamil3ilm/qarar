<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CostingRun;
use App\Models\Manufacturing\CostingVersion;
use App\Models\Manufacturing\CostVariance;
use App\Models\Manufacturing\ProductStandardCost;
use App\Models\Manufacturing\WipValuation;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\ProductCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCostingController extends Controller
{
    public function __construct(
        private ProductCostingService $costingService,
    ) {}

    // -------------------------------------------------------------------------
    // Costing Versions
    // -------------------------------------------------------------------------

    /**
     * List costing versions for the current organisation.
     */
    public function indexVersions(Request $request): JsonResponse
    {
        $query = CostingVersion::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->costing_type, fn ($q, $v) => $q->where('costing_type', $v))
            ->when($request->search, fn ($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->where('version_code', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            }))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['version_code', 'valid_from', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Create a new costing version.
     */
    public function storeVersion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_code'  => 'required|string|max:20',
            'description'   => 'required|string|max:200',
            'valid_from'    => 'required|date',
            'valid_to'      => 'nullable|date|after_or_equal:valid_from',
            'costing_type'  => 'required|in:standard,actual,planned',
            'currency_code' => 'nullable|string|size:3',
        ]);

        $version = CostingVersion::create(array_merge($data, [
            'status'     => CostingVersion::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]));

        return $this->success($version, 'Costing version created.', 201);
    }

    /**
     * Show a single costing version.
     */
    public function showVersion(CostingVersion $version): JsonResponse
    {
        $version->loadCount(['standardCosts', 'costingRuns']);

        return $this->success($version);
    }

    // -------------------------------------------------------------------------
    // Costing Runs
    // -------------------------------------------------------------------------

    /**
     * Trigger a costing run for the given version.
     */
    public function runCosting(Request $request, CostingVersion $version): JsonResponse
    {
        if (!$version->isDraft() && !$version->isActive()) {
            return $this->error('Only draft or active versions can be processed.', 422);
        }

        $organization = $this->organization($request)
            ?? auth()->user()->organization;

        $run = $this->costingService->runCostingRun($version, $organization);

        return $this->success($run, 'Costing run completed.', 201);
    }

    /**
     * Show a costing run's status and summary.
     */
    public function showRunStatus(CostingRun $run): JsonResponse
    {
        $run->loadMissing('costingVersion');

        return $this->success($run);
    }

    // -------------------------------------------------------------------------
    // Standard Costs
    // -------------------------------------------------------------------------

    /**
     * List standard costs for a version.
     */
    public function indexStandardCosts(Request $request, CostingVersion $version): JsonResponse
    {
        $query = ProductStandardCost::where('costing_version_id', $version->id)
            ->with(['product', 'variant'])
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->orderBy('id');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * Show the standard cost for a specific product within a version.
     */
    public function showProductCost(CostingVersion $version, int $productId): JsonResponse
    {
        $cost = ProductStandardCost::where('costing_version_id', $version->id)
            ->where('product_id', $productId)
            ->with(['product', 'components'])
            ->firstOrFail();

        return $this->success($cost);
    }

    // -------------------------------------------------------------------------
    // Variance Analysis
    // -------------------------------------------------------------------------

    /**
     * Calculate and persist variance for a work order.
     */
    public function calculateVariance(WorkOrder $workOrder): JsonResponse
    {
        $variance = $this->costingService->calculateVariance($workOrder);

        return $this->success($variance, 'Variance calculated.');
    }

    /**
     * List cost variances for the organisation with optional period filters.
     */
    public function indexVariances(Request $request): JsonResponse
    {
        $query = CostVariance::with(['workOrder', 'costingVersion'])
            ->when($request->period_year, fn ($q, $y) => $q->where('period_year', $y))
            ->when($request->period_month, fn ($q, $m) => $q->where('period_month', $m))
            ->when($request->work_order_id, fn ($q, $id) => $q->where('work_order_id', $id))
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    // -------------------------------------------------------------------------
    // WIP Valuations
    // -------------------------------------------------------------------------

    /**
     * Run WIP valuation for all open work orders as of a given date.
     */
    public function valuateWip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'valuation_date' => 'required|date',
        ]);

        $organization = $this->organization($request)
            ?? auth()->user()->organization;

        $valuations = $this->costingService->valuateWip($organization, $data['valuation_date']);

        return $this->success([
            'count'       => count($valuations),
            'valuations'  => $valuations,
        ], 'WIP valuation completed.');
    }

    /**
     * List WIP valuations with optional filters.
     */
    public function indexWipValuations(Request $request): JsonResponse
    {
        $query = WipValuation::with('workOrder')
            ->when($request->valuation_date, fn ($q, $d) => $q->where('valuation_date', $d))
            ->when($request->work_order_id, fn ($q, $id) => $q->where('work_order_id', $id))
            ->orderBy('valuation_date', 'desc');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }
}
