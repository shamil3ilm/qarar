<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionResourceTool;
use App\Models\Manufacturing\PrtOperationAssignment;
use App\Services\Manufacturing\ProductionResourceToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionResourceToolController extends Controller
{
    public function __construct(
        private readonly ProductionResourceToolService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'prt_type', 'search']);
        $tools = $this->service->list($filters);

        return $this->success($tools, 'Production resource tools retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'prt_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('production_resource_tools')->where('organization_id', $orgId),
            ],
            'prt_name' => 'required|string|max:100',
            'prt_type' => 'nullable|in:tool,fixture,jig,test_equipment,document,program',
            'status' => 'nullable|in:available,in_use,maintenance,retired',
            'location' => 'nullable|string|max:100',
            'quantity_available' => 'nullable|integer|min:1',
            'serial_number' => 'nullable|string|max:100',
            'next_calibration_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $tool = $this->service->create($validated);

        return $this->created($tool, 'Production resource tool created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $tool = ProductionResourceTool::with('assignments')->findOrFail($id);

        return $this->success($tool);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $tool = ProductionResourceTool::findOrFail($id);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'prt_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('production_resource_tools')->where('organization_id', $orgId)->ignore($tool->id),
            ],
            'prt_name' => 'sometimes|required|string|max:100',
            'prt_type' => 'nullable|in:tool,fixture,jig,test_equipment,document,program',
            'status' => 'nullable|in:available,in_use,maintenance,retired',
            'location' => 'nullable|string|max:100',
            'quantity_available' => 'nullable|integer|min:1',
            'serial_number' => 'nullable|string|max:100',
            'next_calibration_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $updated = $this->service->update($tool, $validated);

        return $this->success($updated, 'Production resource tool updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tool = ProductionResourceTool::findOrFail($id);
        $tool->delete();

        return $this->noContent();
    }

    public function assign(int $id, Request $request): JsonResponse
    {
        $tool = ProductionResourceTool::findOrFail($id);

        $validated = $request->validate([
            'work_order_id' => 'nullable|exists:work_orders,id',
            'routing_operation_id' => 'nullable|exists:routing_operations,id',
            'usage_type' => 'nullable|in:required,optional',
            'quantity_required' => 'nullable|integer|min:1',
        ]);

        $assignment = $this->service->assign($tool, $validated);

        return $this->created($assignment, 'Tool assigned successfully.');
    }

    public function release(int $id, int $assignmentId): JsonResponse
    {
        ProductionResourceTool::findOrFail($id);
        $assignment = PrtOperationAssignment::where('production_resource_tool_id', $id)
            ->findOrFail($assignmentId);

        $this->service->release($assignment);

        return $this->success(null, 'Tool assignment released successfully.');
    }

    public function getForWorkOrder(int $workOrderId): JsonResponse
    {
        $assignments = $this->service->getForWorkOrder($workOrderId);

        return $this->success($assignments, 'Tool assignments retrieved for work order.');
    }

    public function getAvailable(Request $request): JsonResponse
    {
        $type = $request->string('type')->value() ?: null;
        $tools = $this->service->getAvailable($type);

        return $this->success($tools, 'Available tools retrieved successfully.');
    }
}
