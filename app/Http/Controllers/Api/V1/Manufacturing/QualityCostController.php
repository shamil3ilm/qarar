<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\QualityCostEntry;
use App\Services\Manufacturing\QualityCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityCostController extends Controller
{
    public function __construct(private readonly QualityCostService $service) {}

    public function index(Request $request): JsonResponse
    {
        $entries = $this->service->list($request->user()->organization_id, $request->query());
        return $this->success($entries, 'Quality cost entries retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_category'    => 'required|in:prevention,appraisal,internal_failure,external_failure',
            'cost_subcategory' => 'nullable|string|max:100',
            'reference_type'   => 'nullable|string|max:50',
            'reference_id'     => 'nullable|integer',
            'product_id'       => 'nullable|integer|exists:products,id',
            'period'           => 'required|integer|min:1|max:12',
            'fiscal_year'      => 'required|integer|min:2000|max:2100',
            'amount'           => 'required|numeric|min:0',
            'description'      => 'nullable|string',
            'recorded_by'      => 'nullable|integer|exists:users,id',
        ]);

        $entry = $this->service->create($request->user()->organization_id, $data);
        return $this->created($entry, 'Quality cost entry created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $entry = QualityCostEntry::where('organization_id', $request->user()->organization_id)
            ->with(['product', 'recorder'])
            ->findOrFail($id);
        return $this->success($entry, 'Entry retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $entry = QualityCostEntry::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'cost_category'    => 'in:prevention,appraisal,internal_failure,external_failure',
            'cost_subcategory' => 'nullable|string|max:100',
            'reference_type'   => 'nullable|string|max:50',
            'reference_id'     => 'nullable|integer',
            'product_id'       => 'nullable|integer|exists:products,id',
            'period'           => 'integer|min:1|max:12',
            'fiscal_year'      => 'integer|min:2000|max:2100',
            'amount'           => 'numeric|min:0',
            'description'      => 'nullable|string',
        ]);

        $updated = $this->service->update($entry, $data);
        return $this->success($updated, 'Entry updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = QualityCostEntry::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $this->service->delete($entry);
        return $this->success(null, 'Entry deleted.');
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period'      => 'required|integer|min:1|max:12',
            'fiscal_year' => 'required|integer|min:2000|max:2100',
        ]);

        $summary = $this->service->getSummary(
            (int) $data['period'],
            (int) $data['fiscal_year'],
            $request->user()->organization_id
        );

        return $this->success($summary, 'Quality cost summary retrieved.');
    }

    public function trend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'months' => 'integer|min:1|max:36',
        ]);

        $trend = $this->service->getTrend(
            (int) ($data['months'] ?? 12),
            $request->user()->organization_id
        );

        return $this->success($trend, 'Quality cost trend retrieved.');
    }
}
