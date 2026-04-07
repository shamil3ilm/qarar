<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\AppraisalCycle;
use App\Models\HR\AppraisalTemplate;
use App\Models\HR\AppraisalTemplateQuestion;
use App\Models\HR\PerformanceAppraisal;
use App\Models\HR\PerformanceGoal;
use App\Services\HR\PerformanceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function __construct(
        private PerformanceManagementService $service
    ) {}

    // =========================================================================
    // Appraisal Cycles
    // =========================================================================

    public function indexCycles(Request $request): JsonResponse
    {
        $query = AppraisalCycle::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at');

        $cycles = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($cycles);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after_or_equal:review_period_start',
            'self_review_deadline' => 'nullable|date',
            'manager_review_deadline' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $cycle = $this->service->createCycle($validated, auth()->id());

        return $this->created($cycle, 'Appraisal cycle created successfully.');
    }

    public function showCycle(int $id): JsonResponse
    {
        $cycle = AppraisalCycle::with('creator')->find($id);

        if ($cycle === null) {
            return $this->notFound('Appraisal cycle not found.');
        }

        return $this->success($cycle);
    }

    public function updateCycle(Request $request, int $id): JsonResponse
    {
        $cycle = AppraisalCycle::find($id);

        if ($cycle === null) {
            return $this->notFound('Appraisal cycle not found.');
        }

        if ($cycle->status === AppraisalCycle::STATUS_COMPLETED || $cycle->status === AppraisalCycle::STATUS_CANCELLED) {
            return $this->error('Completed or cancelled cycles cannot be updated.', 'CYCLE_NOT_EDITABLE', 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'review_period_start' => 'sometimes|date',
            'review_period_end' => 'sometimes|date|after_or_equal:review_period_start',
            'self_review_deadline' => 'nullable|date',
            'manager_review_deadline' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
            'status' => 'sometimes|in:' . implode(',', AppraisalCycle::STATUSES),
        ]);

        $cycle->update($validated);

        return $this->success($cycle->fresh(), 'Appraisal cycle updated successfully.');
    }

    public function activateCycle(int $id): JsonResponse
    {
        $cycle = AppraisalCycle::find($id);

        if ($cycle === null) {
            return $this->notFound('Appraisal cycle not found.');
        }

        return $this->tryAction(
            fn() => $this->service->activateCycle($cycle, auth()->id()),
            'Appraisal cycle activated and appraisals generated.',
            'CYCLE_ACTIVATION_ERROR'
        );
    }

    public function completeCycle(int $id): JsonResponse
    {
        $cycle = AppraisalCycle::find($id);

        if ($cycle === null) {
            return $this->notFound('Appraisal cycle not found.');
        }

        return $this->tryAction(
            fn() => $this->service->completeCycle($cycle, auth()->id()),
            'Appraisal cycle completed successfully.',
            'CYCLE_COMPLETE_ERROR'
        );
    }

    public function cycleStatistics(int $id): JsonResponse
    {
        $cycle = AppraisalCycle::find($id);

        if ($cycle === null) {
            return $this->notFound('Appraisal cycle not found.');
        }

        $stats = $this->service->getCycleStatistics($cycle);

        return $this->success($stats);
    }

    // =========================================================================
    // Appraisal Templates
    // =========================================================================

    public function indexTemplates(Request $request): JsonResponse
    {
        $query = AppraisalTemplate::query()
            ->when($request->boolean('active_only', false), fn ($q) => $q->active())
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        $templates = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'rating_scale' => 'nullable|integer|min:2|max:10',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sections' => 'required|array|min:1',
            'sections.*.name' => 'required|string|max:255',
            'sections.*.description' => 'nullable|string|max:1000',
            'sections.*.weight_percent' => 'nullable|numeric|min:0|max:100',
            'sections.*.sort_order' => 'nullable|integer|min:0',
            'sections.*.questions' => 'nullable|array',
            'sections.*.questions.*.question' => 'required|string|max:1000',
            'sections.*.questions.*.question_type' => 'nullable|in:' . implode(',', AppraisalTemplateQuestion::TYPES),
            'sections.*.questions.*.is_required' => 'nullable|boolean',
            'sections.*.questions.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $sections = $validated['sections'];
        unset($validated['sections']);

        $template = $this->service->createTemplate($validated, $sections, auth()->id());

        return $this->created($template, 'Appraisal template created successfully.');
    }

    public function showTemplate(int $id): JsonResponse
    {
        $template = AppraisalTemplate::with('sectionsWithQuestions')->find($id);

        if ($template === null) {
            return $this->notFound('Appraisal template not found.');
        }

        return $this->success($template);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = AppraisalTemplate::find($id);

        if ($template === null) {
            return $this->notFound('Appraisal template not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'rating_scale' => 'nullable|integer|min:2|max:10',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        // Handle default flag: unset other defaults in the org first
        if (!empty($validated['is_default'])) {
            AppraisalTemplate::where('organization_id', $template->organization_id)
                ->where('id', '!=', $template->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return $this->success($template->fresh('sectionsWithQuestions'), 'Template updated successfully.');
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        $template = AppraisalTemplate::find($id);

        if ($template === null) {
            return $this->notFound('Appraisal template not found.');
        }

        // Prevent deletion if used in active cycles
        $inUse = PerformanceAppraisal::where('appraisal_template_id', $template->id)
            ->whereHas('cycle', fn ($q) => $q->whereNotIn('status', [
                AppraisalCycle::STATUS_COMPLETED,
                AppraisalCycle::STATUS_CANCELLED,
            ]))
            ->exists();

        if ($inUse) {
            return $this->error(
                'Template is in use by one or more active appraisal cycles and cannot be deleted.',
                'TEMPLATE_IN_USE',
                422
            );
        }

        $template->delete();

        return $this->success(null, 'Appraisal template deleted successfully.');
    }

    // =========================================================================
    // Performance Appraisals
    // =========================================================================

    public function indexAppraisals(Request $request): JsonResponse
    {
        $query = PerformanceAppraisal::with(['cycle', 'employee', 'reviewer'])
            ->when($request->appraisal_cycle_id, fn ($q, $id) => $q->forCycle((int) $id))
            ->when($request->employee_id, fn ($q, $id) => $q->forEmployee((int) $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        $appraisals = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($appraisals);
    }

    public function showAppraisal(int $id): JsonResponse
    {
        $appraisal = PerformanceAppraisal::with([
            'cycle',
            'employee',
            'reviewer',
            'template.sectionsWithQuestions',
            'selfResponses.question',
            'managerResponses.question',
        ])->find($id);

        if ($appraisal === null) {
            return $this->notFound('Performance appraisal not found.');
        }

        return $this->success($appraisal);
    }

    public function submitSelfReview(Request $request, int $id): JsonResponse
    {
        $appraisal = PerformanceAppraisal::with('cycle')->find($id);

        if ($appraisal === null) {
            return $this->notFound('Performance appraisal not found.');
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|integer|exists:appraisal_template_questions,id',
            'responses.*.rating' => 'nullable|integer|min:1|max:10',
            'responses.*.text_response' => 'nullable|string|max:2000',
            'self_comments' => 'nullable|string|max:3000',
        ]);

        if (!empty($validated['self_comments'])) {
            $appraisal->self_comments = $validated['self_comments'];
        }

        return $this->tryAction(
            fn() => $this->service->submitSelfReview($appraisal, $validated['responses'], auth()->id()),
            'Self-review submitted successfully.',
            'SELF_REVIEW_ERROR'
        );
    }

    public function submitManagerReview(Request $request, int $id): JsonResponse
    {
        $appraisal = PerformanceAppraisal::with('cycle')->find($id);

        if ($appraisal === null) {
            return $this->notFound('Performance appraisal not found.');
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|integer|exists:appraisal_template_questions,id',
            'responses.*.rating' => 'nullable|integer|min:1|max:10',
            'responses.*.text_response' => 'nullable|string|max:2000',
            'manager_comments' => 'nullable|string|max:3000',
        ]);

        $responses = $validated['responses'];

        if (!empty($validated['manager_comments'])) {
            $appraisal->manager_comments = $validated['manager_comments'];
        }

        return $this->tryAction(
            fn() => $this->service->submitManagerReview($appraisal, $responses, auth()->id()),
            'Manager review submitted successfully.',
            'MANAGER_REVIEW_ERROR'
        );
    }

    public function acknowledgeAppraisal(Request $request, int $id): JsonResponse
    {
        $appraisal = PerformanceAppraisal::with('cycle')->find($id);

        if ($appraisal === null) {
            return $this->notFound('Performance appraisal not found.');
        }

        $validated = $request->validate([
            'acknowledgement_comments' => 'nullable|string|max:2000',
        ]);

        return $this->tryAction(
            fn() => $this->service->acknowledgeAppraisal(
                $appraisal,
                $validated['acknowledgement_comments'] ?? '',
                auth()->id()
            ),
            'Appraisal acknowledged successfully.',
            'ACKNOWLEDGE_ERROR'
        );
    }

    // =========================================================================
    // Performance Goals
    // =========================================================================

    public function indexGoals(Request $request): JsonResponse
    {
        $query = PerformanceGoal::with(['employee', 'cycle'])
            ->when($request->employee_id, fn ($q, $id) => $q->forEmployee((int) $id))
            ->when($request->appraisal_cycle_id, fn ($q, $id) => $q->forCycle((int) $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        $goals = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($goals);
    }

    public function storeGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'appraisal_cycle_id' => 'nullable|integer|exists:appraisal_cycles,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'target_date' => 'nullable|date',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:' . implode(',', PerformanceGoal::STATUSES),
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $goal = $this->service->createGoal($validated, auth()->id());

        return $this->created($goal, 'Performance goal created successfully.');
    }

    public function showGoal(int $id): JsonResponse
    {
        $goal = PerformanceGoal::with(['employee', 'cycle', 'updates.updatedBy'])->find($id);

        if ($goal === null) {
            return $this->notFound('Performance goal not found.');
        }

        return $this->success($goal);
    }

    public function updateGoal(Request $request, int $id): JsonResponse
    {
        $goal = PerformanceGoal::find($id);

        if ($goal === null) {
            return $this->notFound('Performance goal not found.');
        }

        if (in_array($goal->status, [PerformanceGoal::STATUS_COMPLETED, PerformanceGoal::STATUS_CANCELLED], true)) {
            return $this->error('Completed or cancelled goals cannot be edited.', 'GOAL_NOT_EDITABLE', 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'target_date' => 'nullable|date',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:' . implode(',', PerformanceGoal::STATUSES),
            'self_rating' => 'nullable|integer|min:1|max:10',
            'manager_rating' => 'nullable|integer|min:1|max:10',
            'self_comments' => 'nullable|string|max:2000',
            'manager_comments' => 'nullable|string|max:2000',
        ]);

        $goal->update($validated);

        return $this->success($goal->fresh(), 'Performance goal updated successfully.');
    }

    public function destroyGoal(int $id): JsonResponse
    {
        $goal = PerformanceGoal::find($id);

        if ($goal === null) {
            return $this->notFound('Performance goal not found.');
        }

        $goal->delete();

        return $this->success(null, 'Performance goal deleted successfully.');
    }

    public function updateGoalProgress(Request $request, int $id): JsonResponse
    {
        $goal = PerformanceGoal::find($id);

        if ($goal === null) {
            return $this->notFound('Performance goal not found.');
        }

        if ($goal->status === PerformanceGoal::STATUS_CANCELLED) {
            return $this->error('Progress cannot be updated on a cancelled goal.', 'GOAL_CANCELLED', 422);
        }

        $validated = $request->validate([
            'progress_percent' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $update = $this->service->updateGoalProgress(
            $goal,
            $validated['progress_percent'],
            $validated['notes'] ?? '',
            auth()->id()
        );

        return $this->success([
            'goal' => $goal->fresh(),
            'update' => $update,
        ], 'Goal progress updated successfully.');
    }
}
