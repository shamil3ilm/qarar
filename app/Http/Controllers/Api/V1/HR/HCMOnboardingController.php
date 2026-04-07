<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeOnboarding;
use App\Services\HR\HCMOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HCMOnboardingController extends Controller
{
    public function __construct(
        private readonly HCMOnboardingService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['status', 'employee_id', 'template_type', 'per_page']),
            ['organization_id' => $this->organizationId()]
        );

        return $this->paginated($this->service->list($filters));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'            => 'required|integer|exists:employees,id',
            'template_type'          => 'nullable|in:standard,probation,rehire,transfer_in',
            'started_date'           => 'nullable|date',
            'target_completion_date' => 'nullable|date|after_or_equal:started_date',
            'notes'                  => 'nullable|string',
            'tasks'                  => 'nullable|array',
            'tasks.*.title'          => 'required_with:tasks|string|max:255',
            'tasks.*.category'       => 'nullable|in:hr,it,manager,employee,legal,finance',
            'tasks.*.due_date'       => 'nullable|date',
            'tasks.*.is_required'    => 'nullable|boolean',
            'tasks.*.assigned_to'    => 'nullable|integer|exists:users,id',
        ]);

        $onboarding = $this->service->create($validated, $this->userId());

        return $this->created($onboarding, 'Onboarding started.');
    }

    public function show(EmployeeOnboarding $onboarding): JsonResponse
    {
        return $this->success($this->service->show($onboarding));
    }

    public function update(Request $request, EmployeeOnboarding $onboarding): JsonResponse
    {
        $validated = $request->validate([
            'template_type'          => 'nullable|in:standard,probation,rehire,transfer_in',
            'started_date'           => 'nullable|date',
            'target_completion_date' => 'nullable|date',
            'notes'                  => 'nullable|string',
        ]);

        return $this->success($this->service->update($onboarding, $validated));
    }

    public function cancel(EmployeeOnboarding $onboarding): JsonResponse
    {
        return $this->success($this->service->cancel($onboarding), 'Onboarding cancelled.');
    }

    public function updateTask(Request $request, EmployeeOnboarding $onboarding, int $taskId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,done,skipped',
            'notes'  => 'nullable|string',
        ]);

        $task = $this->service->updateTask(
            $onboarding,
            $taskId,
            $validated['status'],
            $this->userId(),
            $validated['notes'] ?? null,
        );

        return $this->success($task);
    }

    public function addTask(Request $request, EmployeeOnboarding $onboarding): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|in:hr,it,manager,employee,legal,finance',
            'due_date'    => 'nullable|date',
            'is_required' => 'nullable|boolean',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $task = $this->service->addTask($onboarding, $validated, $this->userId());

        return $this->created($task, 'Task added.');
    }

    public function myTasks(): JsonResponse
    {
        return $this->success($this->service->myTasks($this->userId(), $this->organizationId()));
    }
}
