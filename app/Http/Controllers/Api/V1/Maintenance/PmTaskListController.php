<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PmTaskList;
use App\Models\Maintenance\PmTaskListOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmTaskListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PmTaskList::query()
            ->with('operations')
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('search'), fn($q) => $q->where('description', 'like', "%{$request->search}%"));

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_list_number'                        => 'required|string|max:20',
            'description'                             => 'required|string|max:255',
            'operations'                              => 'array',
            'operations.*.operation_number'           => 'required|string|max:10',
            'operations.*.description'                => 'required|string|max:255',
            'operations.*.work_center_id'             => 'nullable|exists:work_centers,id',
            'operations.*.planned_hours'              => 'nullable|numeric|min:0',
        ]);

        $taskList = PmTaskList::create([
            'organization_id'  => $request->user()->organization_id,
            'task_list_number' => $validated['task_list_number'],
            'description'      => $validated['description'],
        ]);

        foreach ($validated['operations'] ?? [] as $op) {
            $taskList->operations()->create($op);
        }

        return $this->created($taskList->load('operations'));
    }

    public function show(PmTaskList $pmTaskList): JsonResponse
    {
        return $this->success($pmTaskList->load('operations'));
    }

    public function update(Request $request, PmTaskList $pmTaskList): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
        ]);

        $pmTaskList->update($validated);

        return $this->success($pmTaskList->load('operations'));
    }

    public function destroy(PmTaskList $pmTaskList): JsonResponse
    {
        $pmTaskList->delete();

        return $this->success(null, 'Task list deleted');
    }

    public function storeOperation(Request $request, PmTaskList $pmTaskList): JsonResponse
    {
        $validated = $request->validate([
            'operation_number' => 'required|string|max:10',
            'description'      => 'required|string|max:255',
            'work_center_id'   => 'nullable|exists:work_centers,id',
            'planned_hours'    => 'nullable|numeric|min:0',
        ]);

        $op = $pmTaskList->operations()->create($validated);

        return $this->created($op);
    }

    public function destroyOperation(PmTaskList $pmTaskList, PmTaskListOperation $operation): JsonResponse
    {
        $operation->delete();

        return $this->success(null, 'Operation removed');
    }
}
