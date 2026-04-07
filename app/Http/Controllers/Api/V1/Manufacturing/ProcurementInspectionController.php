<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProcurementInspection;
use App\Models\Manufacturing\ProcurementInspectionConfig;
use App\Services\Manufacturing\ProcurementInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementInspectionController extends Controller
{
    public function __construct(
        private ProcurementInspectionService $inspectionService,
    ) {}

    // -------------------------------------------------------------------------
    // Configs
    // -------------------------------------------------------------------------

    public function configs(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = ProcurementInspectionConfig::where('organization_id', $orgId)
            ->with(['product', 'vendor', 'qualityPlan'])
            ->when($request->filled('product_id'), fn($q) => $q->where('product_id', $request->input('product_id')))
            ->when($request->filled('vendor_id'), fn($q) => $q->where('vendor_id', $request->input('vendor_id')))
            ->when($request->boolean('active_only'), fn($q) => $q->where('is_active', true));

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function storeConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'                     => 'nullable|integer|exists:products,id',
            'vendor_id'                      => 'nullable|integer|exists:contacts,id',
            'inspection_required'            => 'boolean',
            'sampling_percentage'            => 'numeric|min:0.01|max:100',
            'auto_approve_below_defect_rate' => 'nullable|numeric|min:0|max:100',
            'quality_plan_id'                => 'nullable|integer|exists:quality_plans,id',
            'is_active'                      => 'boolean',
        ]);

        $config = ProcurementInspectionConfig::create([
            'organization_id' => $this->organizationId($request),
            ...$validated,
        ]);

        return $this->created($config->load(['product', 'vendor', 'qualityPlan']));
    }

    public function updateConfig(Request $request, int $id): JsonResponse
    {
        $config = ProcurementInspectionConfig::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($config === null) {
            return $this->notFound('Procurement inspection config not found.');
        }

        $validated = $request->validate([
            'product_id'                     => 'nullable|integer|exists:products,id',
            'vendor_id'                      => 'nullable|integer|exists:contacts,id',
            'inspection_required'            => 'boolean',
            'sampling_percentage'            => 'numeric|min:0.01|max:100',
            'auto_approve_below_defect_rate' => 'nullable|numeric|min:0|max:100',
            'quality_plan_id'                => 'nullable|integer|exists:quality_plans,id',
            'is_active'                      => 'boolean',
        ]);

        $config->update($validated);

        return $this->success($config->fresh(['product', 'vendor', 'qualityPlan']));
    }

    // -------------------------------------------------------------------------
    // Inspections
    // -------------------------------------------------------------------------

    public function inspections(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = ProcurementInspection::where('organization_id', $orgId)
            ->with(['product', 'vendor', 'inspector', 'purchaseOrder'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('vendor_id'), fn($q) => $q->where('vendor_id', $request->input('vendor_id')))
            ->when($request->filled('product_id'), fn($q) => $q->where('product_id', $request->input('product_id')))
            ->when($request->filled('purchase_order_id'), fn($q) => $q->where('purchase_order_id', $request->input('purchase_order_id')));

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function showInspection(Request $request, int $id): JsonResponse
    {
        $inspection = ProcurementInspection::where('organization_id', $this->organizationId($request))
            ->with(['product', 'vendor', 'inspector', 'results', 'inspectionLot', 'purchaseOrder'])
            ->find($id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        return $this->success($inspection);
    }

    public function createInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'goods_receipt_id'  => 'nullable|integer',
            'product_id'        => 'required|integer|exists:products,id',
            'vendor_id'         => 'nullable|integer|exists:contacts,id',
            'inspection_lot_id' => 'nullable|integer|exists:inspection_lots,id',
            'quantity_received' => 'required|numeric|min:0.0001',
            'notes'             => 'nullable|string',
        ]);

        $inspection = $this->inspectionService->createInspection($validated);

        return $this->created($inspection->load(['product', 'vendor']));
    }

    public function recordResults(Request $request, int $id): JsonResponse
    {
        $inspection = ProcurementInspection::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        if (!in_array($inspection->status, [
            ProcurementInspection::STATUS_PENDING,
            ProcurementInspection::STATUS_IN_PROGRESS,
        ], true)) {
            return $this->error('Results can only be recorded on pending or in-progress inspections.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'quantity_accepted'     => 'required|numeric|min:0',
            'quantity_rejected'     => 'required|numeric|min:0',
            'inspected_by'          => 'nullable|integer|exists:users,id',
            'characteristics'       => 'nullable|array',
            'characteristics.*.characteristic_name' => 'required_with:characteristics|string|max:100',
            'characteristics.*.specification_min'   => 'nullable|string|max:50',
            'characteristics.*.specification_max'   => 'nullable|string|max:50',
            'characteristics.*.actual_value'        => 'nullable|string|max:100',
            'characteristics.*.is_within_spec'      => 'nullable|boolean',
            'characteristics.*.defect_description'  => 'nullable|string',
        ]);

        $this->inspectionService->recordResults($inspection, $validated);

        return $this->success($inspection->fresh(['results', 'product', 'vendor']));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $inspection = ProcurementInspection::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        if ($inspection->status !== ProcurementInspection::STATUS_COMPLETED) {
            return $this->error('Only completed inspections can be approved.', 'INVALID_STATUS', 422);
        }

        $this->inspectionService->approveInspection($inspection);

        return $this->success($inspection->fresh());
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $inspection = ProcurementInspection::where('organization_id', $this->organizationId($request))
            ->find($id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        if ($inspection->status !== ProcurementInspection::STATUS_COMPLETED) {
            return $this->error('Only completed inspections can be rejected.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->inspectionService->rejectInspection($inspection, $validated['reason']);

        return $this->success($inspection->fresh());
    }

    public function vendorQualityScore(Request $request, int $vendorId): JsonResponse
    {
        $score = $this->inspectionService->getVendorQualityScore($vendorId);

        return $this->success(['vendor_id' => $vendorId, ...$score]);
    }
}
