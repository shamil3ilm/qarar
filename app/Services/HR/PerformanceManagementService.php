<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\AppraisalCycle;
use App\Models\HR\AppraisalResponse;
use App\Models\HR\AppraisalTemplate;
use App\Models\HR\AppraisalTemplateSection;
use App\Models\HR\Employee;
use App\Models\HR\PerformanceAppraisal;
use App\Models\HR\PerformanceGoal;
use App\Models\HR\PerformanceGoalUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceManagementService
{
    // ---------------------------------------------------------------------------
    // Appraisal Cycles
    // ---------------------------------------------------------------------------

    /**
     * Create a new appraisal cycle (always starts in draft).
     */
    public function createCycle(array $data, int $userId): AppraisalCycle
    {
        return DB::transaction(function () use ($data, $userId): AppraisalCycle {
            return AppraisalCycle::create([
                'organization_id' => $data['organization_id'],
                'name' => $data['name'],
                'review_period_start' => $data['review_period_start'],
                'review_period_end' => $data['review_period_end'],
                'self_review_deadline' => $data['self_review_deadline'] ?? null,
                'manager_review_deadline' => $data['manager_review_deadline'] ?? null,
                'status' => AppraisalCycle::STATUS_DRAFT,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Activate a cycle: transition to active status and generate appraisal records
     * for every currently-active employee in the organisation.
     */
    public function activateCycle(AppraisalCycle $cycle, int $userId): AppraisalCycle
    {
        if ($cycle->status !== AppraisalCycle::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft cycles can be activated.');
        }

        return DB::transaction(function () use ($cycle): AppraisalCycle {
            $cycle->status = AppraisalCycle::STATUS_ACTIVE;
            $cycle->save();

            // Determine default template (if any)
            $defaultTemplate = AppraisalTemplate::where('organization_id', $cycle->organization_id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            // Generate one appraisal record per active employee
            $employees = Employee::where('organization_id', $cycle->organization_id)
                ->where('is_active', true)
                ->where('employment_status', Employee::STATUS_ACTIVE)
                ->get();

            foreach ($employees as $employee) {
                // Avoid duplicates if called more than once
                PerformanceAppraisal::firstOrCreate(
                    [
                        'appraisal_cycle_id' => $cycle->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'organization_id' => $cycle->organization_id,
                        'reviewer_id' => $employee->reporting_manager_id ?? null,
                        'appraisal_template_id' => $defaultTemplate?->id,
                        'status' => PerformanceAppraisal::STATUS_PENDING,
                    ]
                );
            }

            Log::info('Appraisal cycle activated', [
                'cycle_id' => $cycle->id,
                'employees_count' => $employees->count(),
            ]);

            return $cycle->fresh();
        });
    }

    /**
     * Submit self-review responses and transition appraisal to self_review_submitted.
     *
     * @param  array  $responses  Array of ['question_id' => int, 'rating' => int|null, 'text_response' => string|null]
     */
    public function submitSelfReview(
        PerformanceAppraisal $appraisal,
        array $responses,
        int $userId
    ): PerformanceAppraisal {
        if ($appraisal->status !== PerformanceAppraisal::STATUS_PENDING) {
            throw new \InvalidArgumentException('Self-review can only be submitted when the appraisal is in pending status.');
        }

        if (!$appraisal->cycle->canSubmitSelfReview()) {
            throw new \InvalidArgumentException('The appraisal cycle is not currently open for self-review submissions.');
        }

        return DB::transaction(function () use ($appraisal, $responses): PerformanceAppraisal {
            if ($appraisal->template === null) {
                throw new \RuntimeException('Cannot submit appraisal: no template assigned.');
            }

            // Upsert responses
            foreach ($responses as $responseData) {
                AppraisalResponse::updateOrCreate(
                    [
                        'performance_appraisal_id' => $appraisal->id,
                        'appraisal_template_question_id' => $responseData['question_id'],
                        'respondent_type' => AppraisalResponse::RESPONDENT_SELF,
                    ],
                    [
                        'rating' => $responseData['rating'] ?? null,
                        'text_response' => $responseData['text_response'] ?? null,
                    ]
                );
            }

            // Recompute overall self rating
            $appraisal->load('template.sections.questions', 'responses');
            $overallSelfRating = $appraisal->calculateOverallRating('self');

            $appraisal->status = PerformanceAppraisal::STATUS_SELF_REVIEW_SUBMITTED;
            $appraisal->self_submitted_at = now();
            $appraisal->overall_self_rating = $overallSelfRating > 0.0 ? $overallSelfRating : null;
            $appraisal->save();

            return $appraisal->fresh();
        });
    }

    /**
     * Submit manager-review responses and transition appraisal to manager_review_submitted.
     *
     * @param  array  $responses  Array of ['question_id' => int, 'rating' => int|null, 'text_response' => string|null]
     */
    public function submitManagerReview(
        PerformanceAppraisal $appraisal,
        array $responses,
        int $userId
    ): PerformanceAppraisal {
        if ($appraisal->status !== PerformanceAppraisal::STATUS_SELF_REVIEW_SUBMITTED) {
            throw new \InvalidArgumentException('Manager review can only be submitted after the employee has completed the self-review.');
        }

        if ($appraisal->reviewer_id !== null && $appraisal->reviewer_id === $appraisal->employee_id) {
            throw new \RuntimeException('An employee cannot appraise themselves.');
        }

        if (!$appraisal->cycle->canSubmitManagerReview()) {
            throw new \InvalidArgumentException('The appraisal cycle is not currently open for manager-review submissions.');
        }

        return DB::transaction(function () use ($appraisal, $responses): PerformanceAppraisal {
            if ($appraisal->template === null) {
                throw new \RuntimeException('Cannot submit appraisal: no template assigned.');
            }

            foreach ($responses as $responseData) {
                AppraisalResponse::updateOrCreate(
                    [
                        'performance_appraisal_id' => $appraisal->id,
                        'appraisal_template_question_id' => $responseData['question_id'],
                        'respondent_type' => AppraisalResponse::RESPONDENT_MANAGER,
                    ],
                    [
                        'rating' => $responseData['rating'] ?? null,
                        'text_response' => $responseData['text_response'] ?? null,
                    ]
                );
            }

            $appraisal->load('template.sections.questions', 'responses');
            $overallManagerRating = $appraisal->calculateOverallRating('manager');

            $appraisal->status = PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED;
            $appraisal->manager_submitted_at = now();
            $appraisal->overall_manager_rating = $overallManagerRating > 0.0 ? $overallManagerRating : null;

            // manager_comments may accompany the review submission
            if (isset($responses['manager_comments'])) {
                $appraisal->manager_comments = $responses['manager_comments'];
            }

            $appraisal->save();

            return $appraisal->fresh();
        });
    }

    /**
     * Employee acknowledges the completed appraisal result.
     */
    public function acknowledgeAppraisal(
        PerformanceAppraisal $appraisal,
        string $comments,
        int $userId
    ): PerformanceAppraisal {
        if ($appraisal->status !== PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED) {
            throw new \InvalidArgumentException('An appraisal can only be acknowledged after the manager has submitted their review.');
        }

        return DB::transaction(function () use ($appraisal, $comments): PerformanceAppraisal {
            $appraisal->status = PerformanceAppraisal::STATUS_ACKNOWLEDGED;
            $appraisal->acknowledged_at = now();
            $appraisal->employee_acknowledgement = $comments ?: null;
            $appraisal->save();

            return $appraisal->fresh();
        });
    }

    /**
     * Close the cycle: mark all submitted/acknowledged appraisals as completed
     * and transition the cycle itself to completed.
     */
    public function completeCycle(AppraisalCycle $cycle, int $userId): AppraisalCycle
    {
        $completableStatuses = [
            AppraisalCycle::STATUS_ACTIVE,
            AppraisalCycle::STATUS_SELF_REVIEW,
            AppraisalCycle::STATUS_MANAGER_REVIEW,
            AppraisalCycle::STATUS_CALIBRATION,
        ];

        if (!in_array($cycle->status, $completableStatuses, true)) {
            throw new \InvalidArgumentException('The cycle cannot be completed in its current status.');
        }

        return DB::transaction(function () use ($cycle): AppraisalCycle {
            // Complete all appraisals that have at least had the manager review submitted
            PerformanceAppraisal::where('appraisal_cycle_id', $cycle->id)
                ->whereIn('status', [
                    PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED,
                    PerformanceAppraisal::STATUS_ACKNOWLEDGED,
                ])
                ->get()
                ->each(function (PerformanceAppraisal $appraisal): void {
                    // Set final_rating to manager rating if available, otherwise self rating
                    $appraisal->final_rating = $appraisal->overall_manager_rating
                        ?? $appraisal->overall_self_rating;
                    $appraisal->status = PerformanceAppraisal::STATUS_COMPLETED;
                    $appraisal->save();
                });

            $cycle->status = AppraisalCycle::STATUS_COMPLETED;
            $cycle->save();

            Log::info('Appraisal cycle completed', ['cycle_id' => $cycle->id]);

            return $cycle->fresh();
        });
    }

    // ---------------------------------------------------------------------------
    // Performance Goals
    // ---------------------------------------------------------------------------

    /**
     * Create a performance goal for an employee.
     */
    public function createGoal(array $data, int $userId): PerformanceGoal
    {
        return DB::transaction(function () use ($data, $userId): PerformanceGoal {
            return PerformanceGoal::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'appraisal_cycle_id' => $data['appraisal_cycle_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'target_date' => $data['target_date'] ?? null,
                'weight_percent' => $data['weight_percent'] ?? 0,
                'status' => $data['status'] ?? PerformanceGoal::STATUS_DRAFT,
                'progress_percent' => 0,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Record a progress update against a goal.
     */
    public function updateGoalProgress(
        PerformanceGoal $goal,
        int $percent,
        string $notes,
        int $userId
    ): PerformanceGoalUpdate {
        return DB::transaction(
            fn (): PerformanceGoalUpdate => $goal->updateProgress($percent, $notes, $userId)
        );
    }

    // ---------------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------------

    /**
     * Create an appraisal template with its sections and questions.
     *
     * @param  array  $sections  Array of section data, each optionally containing a 'questions' key.
     */
    public function createTemplate(array $data, array $sections, int $userId): AppraisalTemplate
    {
        return DB::transaction(function () use ($data, $sections, $userId): AppraisalTemplate {
            // Ensure only one default template per organisation
            if (!empty($data['is_default'])) {
                AppraisalTemplate::where('organization_id', $data['organization_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template = AppraisalTemplate::create([
                'organization_id' => $data['organization_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rating_scale' => $data['rating_scale'] ?? 5,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $userId,
            ]);

            foreach ($sections as $sortOrder => $sectionData) {
                $section = $template->sections()->create([
                    'name' => $sectionData['name'],
                    'description' => $sectionData['description'] ?? null,
                    'weight_percent' => $sectionData['weight_percent'] ?? 0,
                    'sort_order' => $sectionData['sort_order'] ?? $sortOrder,
                ]);

                foreach ($sectionData['questions'] ?? [] as $qSortOrder => $questionData) {
                    $section->questions()->create([
                        'question' => $questionData['question'],
                        'question_type' => $questionData['question_type'] ?? 'rating',
                        'is_required' => $questionData['is_required'] ?? true,
                        'sort_order' => $questionData['sort_order'] ?? $qSortOrder,
                    ]);
                }
            }

            return $template->load('sectionsWithQuestions');
        });
    }

    // ---------------------------------------------------------------------------
    // Statistics
    // ---------------------------------------------------------------------------

    /**
     * Return completion statistics for a cycle, broken down by department.
     *
     * Returns:
     *   - total: total appraisals generated
     *   - by_status: count per appraisal status
     *   - completion_rate: % of appraisals in completed/acknowledged/manager_review_submitted
     *   - avg_self_rating: organisation-wide average self rating
     *   - avg_manager_rating: organisation-wide average manager rating
     *   - by_department: per-department breakdown
     */
    public function getCycleStatistics(AppraisalCycle $cycle): array
    {
        $appraisals = PerformanceAppraisal::with('employee.department')
            ->where('appraisal_cycle_id', $cycle->id)
            ->get();

        $total = $appraisals->count();

        $byStatus = $appraisals->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $submitted = $appraisals->whereIn('status', [
            PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED,
            PerformanceAppraisal::STATUS_ACKNOWLEDGED,
            PerformanceAppraisal::STATUS_COMPLETED,
        ])->count();

        $completionRate = $total > 0
            ? (float) bcmul(bcdiv((string) $submitted, (string) $total, 6), '100', 1)
            : 0.0;

        $selfRatings = $appraisals->whereNotNull('overall_self_rating')->pluck('overall_self_rating');
        $managerRatings = $appraisals->whereNotNull('overall_manager_rating')->pluck('overall_manager_rating');

        $avgSelfRating = $selfRatings->isNotEmpty()
            ? round($selfRatings->average(), 2)
            : null;

        $avgManagerRating = $managerRatings->isNotEmpty()
            ? round($managerRatings->average(), 2)
            : null;

        // Per-department breakdown
        $byDepartment = $appraisals
            ->groupBy(fn ($appraisal) => $appraisal->employee?->department?->name ?? 'Unassigned')
            ->map(function ($deptAppraisals, $deptName): array {
                $deptTotal = $deptAppraisals->count();
                $deptSubmitted = $deptAppraisals->whereIn('status', [
                    PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED,
                    PerformanceAppraisal::STATUS_ACKNOWLEDGED,
                    PerformanceAppraisal::STATUS_COMPLETED,
                ])->count();

                $deptSelfRatings = $deptAppraisals->whereNotNull('overall_self_rating')
                    ->pluck('overall_self_rating');
                $deptManagerRatings = $deptAppraisals->whereNotNull('overall_manager_rating')
                    ->pluck('overall_manager_rating');

                return [
                    'department' => $deptName,
                    'total' => $deptTotal,
                    'submitted' => $deptSubmitted,
                    'completion_rate' => $deptTotal > 0
                        ? (float) bcmul(bcdiv((string) $deptSubmitted, (string) $deptTotal, 6), '100', 1)
                        : 0.0,
                    'avg_self_rating' => $deptSelfRatings->isNotEmpty()
                        ? round($deptSelfRatings->average(), 2)
                        : null,
                    'avg_manager_rating' => $deptManagerRatings->isNotEmpty()
                        ? round($deptManagerRatings->average(), 2)
                        : null,
                ];
            })
            ->values()
            ->toArray();

        return [
            'cycle_id' => $cycle->id,
            'cycle_name' => $cycle->name,
            'status' => $cycle->status,
            'total' => $total,
            'by_status' => $byStatus,
            'completion_rate' => $completionRate,
            'avg_self_rating' => $avgSelfRating,
            'avg_manager_rating' => $avgManagerRating,
            'by_department' => $byDepartment,
        ];
    }
}
