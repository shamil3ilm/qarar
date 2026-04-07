<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\EngineeringChange;
use App\Services\Manufacturing\EngineeringChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringChangeController extends Controller
{
    public function __construct(
        private readonly EngineeringChangeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'change_type', 'priority', 'product_id']);
        $changes = $this->service->list($filters);

        return $this->success($changes, 'Engineering changes retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'change_number' => 'required|string|max:50|unique:engineering_changes,change_number,NULL,id,organization_id,' . $orgId,
            'change_type' => 'nullable|in:bom_change,routing_change,product_spec_change,drawing_change',
            'description' => 'required|string',
            'reason' => 'nullable|string',
            'effectivity_date' => 'nullable|date',
            'priority' => 'nullable|in:low,normal,high,critical',
            'requested_by' => 'nullable|exists:users,id',
        ]);

        $ec = $this->service->create($validated);

        return $this->created($ec, 'Engineering change created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $ec = EngineeringChange::with(['requestedBy', 'approvedBy', 'affectedObjects'])
            ->findOrFail($id);

        return $this->success($ec);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'change_number' => 'sometimes|required|string|max:50|unique:engineering_changes,change_number,' . $ec->id . ',id,organization_id,' . $orgId,
            'change_type' => 'nullable|in:bom_change,routing_change,product_spec_change,drawing_change',
            'description' => 'sometimes|required|string',
            'reason' => 'nullable|string',
            'effectivity_date' => 'nullable|date',
            'priority' => 'nullable|in:low,normal,high,critical',
        ]);

        $updated = $this->service->update($ec, $validated);

        return $this->success($updated, 'Engineering change updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);
        $ec->delete();

        return $this->noContent();
    }

    public function submit(int $id): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);
        $updated = $this->service->submit($ec);

        return $this->success($updated, 'Engineering change submitted for approval.');
    }

    public function approve(int $id, Request $request): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);
        $approvedBy = auth()->id();

        $updated = $this->service->approve($ec, $approvedBy);

        return $this->success($updated, 'Engineering change approved.');
    }

    public function reject(int $id, Request $request): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $updated = $this->service->reject($ec, auth()->id(), $validated['reason']);

        return $this->success($updated, 'Engineering change rejected.');
    }

    public function implement(int $id): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);
        $updated = $this->service->implement($ec);

        return $this->success($updated, 'Engineering change implemented.');
    }

    public function addAffectedObject(int $id, Request $request): JsonResponse
    {
        $ec = EngineeringChange::findOrFail($id);

        $validated = $request->validate([
            'object_type' => 'required|in:bom,routing,product,drawing',
            'object_id' => 'required|integer',
            'object_reference' => 'nullable|string|max:100',
            'change_description' => 'nullable|string',
            'before_value' => 'nullable|array',
            'after_value' => 'nullable|array',
        ]);

        $affectedObject = $this->service->addAffectedObject($ec, $validated);

        return $this->created($affectedObject, 'Affected object added to engineering change.');
    }

    public function getForObject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'object_type' => 'required|in:bom,routing,product,drawing',
            'object_id' => 'required|integer',
        ]);

        $changes = $this->service->getChangesForObject($validated['object_type'], (int) $validated['object_id']);

        return $this->success($changes, 'Engineering changes retrieved for object.');
    }
}
