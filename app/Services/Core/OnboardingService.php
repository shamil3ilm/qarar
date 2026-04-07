<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\FeatureAdoptionEvent;
use App\Models\Core\OnboardingStep;
use App\Models\Core\OnboardingTemplate;
use App\Models\Core\UserOnboardingProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    /**
     * Get the active onboarding template for a given module.
     * Org-specific template takes precedence over global (null org) template.
     */
    public function getTemplateForModule(int $organizationId, string $module): ?OnboardingTemplate
    {
        // Try org-specific template first
        $template = OnboardingTemplate::where('organization_id', $organizationId)
            ->where('module', $module)
            ->where('is_active', true)
            ->orderBy('order')
            ->first();

        if ($template !== null) {
            return $template;
        }

        // Fall back to global template
        return OnboardingTemplate::whereNull('organization_id')
            ->where('module', $module)
            ->where('is_active', true)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get all steps for a template with the user's completion status.
     */
    public function getUserProgress(int $organizationId, int $userId, int $templateId): array
    {
        $template = OnboardingTemplate::with('steps')->findOrFail($templateId);

        $progressMap = UserOnboardingProgress::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('template_id', $templateId)
            ->get()
            ->keyBy('step_id');

        $steps = $template->steps->map(function (OnboardingStep $step) use ($progressMap): array {
            $progress = $progressMap->get($step->id);

            return [
                'id'          => $step->id,
                'title'       => $step->title,
                'description' => $step->description,
                'step_type'   => $step->step_type,
                'action_key'  => $step->action_key,
                'help_url'    => $step->help_url,
                'is_required' => $step->is_required,
                'order'       => $step->order,
                'completed'   => $progress?->isCompleted() ?? false,
                'skipped'     => $progress?->isSkipped() ?? false,
                'completed_at' => $progress?->completed_at,
                'skipped_at'  => $progress?->skipped_at,
            ];
        });

        return [
            'template_id'         => $template->id,
            'template_name'       => $template->name,
            'module'              => $template->module,
            'steps'               => $steps,
            'completion_percentage' => $this->getCompletionPercentage($organizationId, $userId, $templateId),
        ];
    }

    /**
     * Mark a step as completed for a user.
     */
    public function completeStep(int $organizationId, int $userId, int $stepId): UserOnboardingProgress
    {
        $step = OnboardingStep::findOrFail($stepId);

        $progress = UserOnboardingProgress::firstOrNew([
            'organization_id' => $organizationId,
            'user_id'         => $userId,
            'template_id'     => $step->template_id,
            'step_id'         => $stepId,
        ]);

        $progress->completed_at = Carbon::now();
        $progress->skipped_at   = null;
        $progress->save();

        return $progress;
    }

    /**
     * Mark a step as skipped for a user.
     */
    public function skipStep(int $organizationId, int $userId, int $stepId): UserOnboardingProgress
    {
        $step = OnboardingStep::findOrFail($stepId);

        $progress = UserOnboardingProgress::firstOrNew([
            'organization_id' => $organizationId,
            'user_id'         => $userId,
            'template_id'     => $step->template_id,
            'step_id'         => $stepId,
        ]);

        // Only allow skipping non-required steps
        if ($step->is_required) {
            throw new \InvalidArgumentException('Required steps cannot be skipped.');
        }

        $progress->skipped_at   = Carbon::now();
        $progress->completed_at = null;
        $progress->save();

        return $progress;
    }

    /**
     * Calculate the completion percentage for a user on a template.
     * Considers steps completed OR skipped as "done".
     */
    public function getCompletionPercentage(int $organizationId, int $userId, int $templateId): float
    {
        $totalSteps = OnboardingStep::where('template_id', $templateId)->count();

        if ($totalSteps === 0) {
            return 100.0;
        }

        $doneSteps = UserOnboardingProgress::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('template_id', $templateId)
            ->where(function ($q): void {
                $q->whereNotNull('completed_at')->orWhereNotNull('skipped_at');
            })
            ->count();

        return round(($doneSteps / $totalSteps) * 100, 2);
    }

    /**
     * Track a feature usage event (upsert).
     */
    public function trackFeatureUse(int $organizationId, int $userId, string $featureKey): void
    {
        $now = Carbon::now();

        $existing = FeatureAdoptionEvent::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('feature_key', $featureKey)
            ->first();

        if ($existing === null) {
            FeatureAdoptionEvent::create([
                'organization_id' => $organizationId,
                'user_id'         => $userId,
                'feature_key'     => $featureKey,
                'first_used_at'   => $now,
                'last_used_at'    => $now,
                'usage_count'     => 1,
            ]);

            return;
        }

        $existing->update([
            'last_used_at' => $now,
            'usage_count'  => DB::raw('usage_count + 1'),
        ]);
    }

    /**
     * Get an adoption summary for an organization.
     */
    public function getAdoptionSummary(int $organizationId): array
    {
        // Per-feature adoption counts (distinct user count + total uses)
        $featureStats = FeatureAdoptionEvent::where('organization_id', $organizationId)
            ->select('feature_key', DB::raw('COUNT(*) as user_count'), DB::raw('SUM(usage_count) as total_uses'))
            ->groupBy('feature_key')
            ->orderByDesc('user_count')
            ->get()
            ->toArray();

        // Top unused features: onboarding steps with action_keys that have no adoption events
        $usedFeatureKeys = FeatureAdoptionEvent::where('organization_id', $organizationId)
            ->distinct()
            ->pluck('feature_key')
            ->toArray();

        $unusedActions = OnboardingStep::whereNotNull('action_key')
            ->whereNotIn('action_key', $usedFeatureKeys)
            ->select('action_key', 'title')
            ->distinct()
            ->limit(10)
            ->get()
            ->toArray();

        // Per-user completion rates across all templates
        $templates = OnboardingTemplate::where(function ($q) use ($organizationId): void {
            $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
        })->where('is_active', true)->get();

        $userCompletionRates = [];
        foreach ($templates as $template) {
            $stepCount = $template->steps()->count();
            if ($stepCount === 0) {
                continue;
            }

            $userRates = UserOnboardingProgress::where('organization_id', $organizationId)
                ->where('template_id', $template->id)
                ->select('user_id', DB::raw('COUNT(*) as done_steps'))
                ->where(function ($q): void {
                    $q->whereNotNull('completed_at')->orWhereNotNull('skipped_at');
                })
                ->groupBy('user_id')
                ->get()
                ->map(fn ($row) => [
                    'user_id'    => $row->user_id,
                    'percentage' => round(($row->done_steps / $stepCount) * 100, 2),
                ])
                ->toArray();

            $userCompletionRates[$template->id] = [
                'template_name' => $template->name,
                'module'        => $template->module,
                'total_steps'   => $stepCount,
                'user_rates'    => $userRates,
            ];
        }

        return [
            'feature_stats'        => $featureStats,
            'top_unused_features'  => $unusedActions,
            'user_completion_rates' => $userCompletionRates,
        ];
    }

    /**
     * Create a new onboarding template.
     */
    public function createTemplate(array $data): OnboardingTemplate
    {
        return OnboardingTemplate::create($data);
    }

    /**
     * Add a step to an existing template.
     */
    public function addStep(int $templateId, array $data): OnboardingStep
    {
        OnboardingTemplate::findOrFail($templateId);

        return OnboardingStep::create(array_merge($data, ['template_id' => $templateId]));
    }
}
