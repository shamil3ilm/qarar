<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ScrapReport;
use App\Services\Manufacturing\ScrapReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrapReportingController extends Controller
{
    public function __construct(
        private readonly ScrapReportingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'work_order_id',
            'product_id',
            'scrap_cause',
            'gl_posted',
            'from_date',
            'to_date',
        ]);

        $reports = $this->service->list($filters);

        return $this->success($reports, 'Scrap reports retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('organization_id', $orgId)],
            'product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'scrap_date' => 'required|date',
            'scrap_quantity' => 'required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'scrap_cause' => 'nullable|in:defect,damage,obsolete,process_loss,machine_failure,other',
            'scrap_code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'is_recoverable' => 'boolean',
            'recovery_value' => 'nullable|numeric|min:0',
            'reported_by' => 'nullable|exists:users,id',
        ]);

        $validated['reported_by'] = $validated['reported_by'] ?? auth()->id();

        $report = $this->service->create($validated);

        return $this->created($report->load(['product', 'workOrder', 'warehouse', 'reportedBy']), 'Scrap report created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $report = ScrapReport::with(['product', 'workOrder', 'warehouse', 'reportedBy'])
            ->findOrFail($id);

        return $this->success($report);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $report = ScrapReport::findOrFail($id);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('organization_id', $orgId)],
            'product_id' => ['sometimes', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
            'scrap_date' => 'sometimes|date',
            'scrap_quantity' => 'sometimes|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'scrap_cause' => 'nullable|in:defect,damage,obsolete,process_loss,machine_failure,other',
            'scrap_code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'is_recoverable' => 'boolean',
            'recovery_value' => 'nullable|numeric|min:0',
        ]);

        $updated = $this->service->update($report, $validated);

        return $this->success($updated, 'Scrap report updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $report = ScrapReport::findOrFail($id);

        if ($report->gl_posted) {
            return $this->error('Cannot delete a scrap report that has been posted to GL.', 'VALIDATION_ERROR', 422);
        }

        $report->delete();

        return $this->noContent();
    }

    public function postToGL(int $id): JsonResponse
    {
        $report = ScrapReport::findOrFail($id);
        $updated = $this->service->postToGL($report);

        return $this->success($updated, 'Scrap report posted to GL successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'work_order_id']);
        $summary = $this->service->getScrapSummary($filters);

        return $this->success($summary, 'Scrap summary retrieved successfully.');
    }
}
