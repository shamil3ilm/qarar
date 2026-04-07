<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Core\OnboardingTemplate;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * List all onboarding templates accessible to the organization.
     */
    public function indexTemplates(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $templates = OnboardingTemplate::where(function ($q) use ($orgId): void {
            $q->where('organization_id', $orgId)->orWhereNull('organization_id');
        })
            ->where('is_active', true)
            ->orderBy('order')
            ->with('steps')
            ->get();

        return $this->success($templates, 'Onboarding templates retrieved successfully.');
    }

    /**
     * Create a new onboarding template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'module'      => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'order'       => 'integer|min:0|max:127',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $template = $this->onboardingService->createTemplate($validated);

        return $this->created($template, 'Onboarding template created successfully.');
    }

    /**
     * Add a step to an existing onboarding template.
     */
    public function addStep(Request $request, int $templateId): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'step_type'   => 'in:action,info,video,link',
            'action_key'  => 'nullable|string|max:100',
            'help_url'    => 'nullable|url|max:500',
            'is_required' => 'boolean',
            'order'       => 'integer|min:0|max:127',
        ]);

        $step = $this->onboardingService->addStep($templateId, $validated);

        return $this->created($step, 'Step added successfully.');
    }

    /**
     * Get a user's onboarding progress for a given template.
     * GET /onboarding/progress/{userId}?template_id=X
     */
    public function getUserProgress(Request $request, int $userId): JsonResponse
    {
        $request->validate(['template_id' => 'required|integer|exists:onboarding_templates,id']);

        $orgId = $this->organizationId($request);
        $templateId = (int) $request->query('template_id');

        $progress = $this->onboardingService->getUserProgress($orgId, $userId, $templateId);

        return $this->success($progress, 'User onboarding progress retrieved successfully.');
    }

    /**
     * Mark a step as completed for the authenticated user.
     * POST /onboarding/steps/{stepId}/complete
     */
    public function completeStep(Request $request, int $stepId): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $userId = $request->user()->id;

        $progress = $this->onboardingService->completeStep($orgId, $userId, $stepId);

        return $this->success($progress, 'Step marked as completed.');
    }

    /**
     * Mark a step as skipped for the authenticated user.
     * POST /onboarding/steps/{stepId}/skip
     */
    public function skipStep(Request $request, int $stepId): JsonResponse
    {
        $orgId  = $this->organizationId($request);
        $userId = $request->user()->id;

        $progress = $this->onboardingService->skipStep($orgId, $userId, $stepId);

        return $this->success($progress, 'Step skipped.');
    }

    /**
     * Track a feature use event for the authenticated user.
     * POST /onboarding/features/track
     */
    public function trackFeature(Request $request): JsonResponse
    {
        $request->validate(['feature_key' => 'required|string|max:100']);

        $orgId  = $this->organizationId($request);
        $userId = $request->user()->id;

        $this->onboardingService->trackFeatureUse($orgId, $userId, $request->input('feature_key'));

        return $this->success(null, 'Feature usage tracked.');
    }

    /**
     * Get feature adoption summary for the organization.
     * GET /onboarding/adoption-summary
     */
    public function adoptionSummary(Request $request): JsonResponse
    {
        $orgId   = $this->organizationId($request);
        $summary = $this->onboardingService->getAdoptionSummary($orgId);

        return $this->success($summary, 'Feature adoption summary retrieved successfully.');
    }
}
