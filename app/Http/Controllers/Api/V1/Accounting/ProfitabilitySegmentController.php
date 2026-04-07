<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ProfitabilitySegment;
use App\Services\Accounting\ProfitabilitySegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitabilitySegmentController extends Controller
{
    public function __construct(
        private readonly ProfitabilitySegmentService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'search']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'segment_name'      => ['required', 'string', 'max:100'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'product_id'        => ['nullable', 'integer', 'exists:products,id'],
            'region'            => ['nullable', 'string', 'max:100'],
            'sales_channel'     => ['nullable', 'string', 'max:100'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $segment = $this->service->create(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($segment);
    }

    public function show(int $id): JsonResponse
    {
        $segment = ProfitabilitySegment::with(['customerGroup', 'product:id,name,sku', 'values'])->findOrFail($id);

        return $this->success($segment);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $segment = ProfitabilitySegment::findOrFail($id);

        $validated = $request->validate([
            'segment_name'      => ['sometimes', 'string', 'max:100'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'product_id'        => ['nullable', 'integer', 'exists:products,id'],
            'region'            => ['nullable', 'string', 'max:100'],
            'sales_channel'     => ['nullable', 'string', 'max:100'],
            'is_active'         => ['sometimes', 'boolean'],
        ]);

        $segment = $this->service->update($segment, $validated);

        return $this->success($segment);
    }

    public function destroy(int $id): JsonResponse
    {
        $segment = ProfitabilitySegment::findOrFail($id);
        $segment->delete();

        return $this->noContent();
    }

    public function postValues(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'profitability_segment_id' => ['required', 'integer', 'exists:profitability_segments,id'],
            'copa_dimension_id'        => ['nullable', 'integer', 'exists:copa_dimensions,id'],
            'period'                   => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year'              => ['required', 'integer'],
            'revenue'                  => ['nullable', 'numeric'],
            'cost_of_sales'            => ['nullable', 'numeric'],
            'gross_margin'             => ['nullable', 'numeric'],
            'overhead_costs'           => ['nullable', 'numeric'],
            'net_margin'               => ['nullable', 'numeric'],
            'quantity_sold'            => ['nullable', 'numeric'],
        ]);

        $value = $this->service->postValues(array_merge($validated, ['organization_id' => $orgId]));

        return $this->success($value);
    }

    public function drillDown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dimensions'  => ['nullable', 'array'],
            'dimensions.*'=> ['string', 'in:region,sales_channel,segment_name'],
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $result = $this->service->getDrillDown(
            $validated['dimensions'] ?? ['segment_name'],
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($result);
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $report = $this->service->getSegmentReport(
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($report);
    }
}
