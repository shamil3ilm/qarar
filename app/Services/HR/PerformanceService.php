<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\AppraisalReviewer;
use App\Models\HR\AppraisalReviewerResponse;
use App\Models\HR\AppraisalTemplateQuestion;
use App\Models\HR\PerformanceAppraisal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceService
{
    /**
     * Assign reviewers to an appraisal and create pending reviewer records.
     *
     * @param  PerformanceAppraisal  $appraisal
     * @param  array<int, array{employee_id: int, type: string, is_anonymous?: bool, due_date?: string|null}>  $reviewers
     */
    public function initiateReviewCycle(PerformanceAppraisal $appraisal, array $reviewers): void
    {
        DB::transaction(function () use ($appraisal, $reviewers): void {
            foreach ($reviewers as $reviewerData) {
                $type = $reviewerData['type'] ?? AppraisalReviewer::TYPE_PEER;

                if (!in_array($type, AppraisalReviewer::TYPES, true)) {
                    throw new InvalidArgumentException("Invalid reviewer type: {$type}");
                }

                // Use updateOrCreate to avoid duplicates when re-running.
                AppraisalReviewer::updateOrCreate(
                    [
                        'appraisal_id'  => $appraisal->id,
                        'reviewer_id'   => $reviewerData['employee_id'],
                        'reviewer_type' => $type,
                    ],
                    [
                        'organization_id' => $appraisal->organization_id,
                        'status'          => AppraisalReviewer::STATUS_PENDING,
                        'is_anonymous'    => (bool) ($reviewerData['is_anonymous'] ?? false),
                        'due_date'        => $reviewerData['due_date'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Submit a 360° reviewer's feedback.
     *
     * @param  AppraisalReviewer  $reviewer
     * @param  array{
     *     overall_rating?: float|null,
     *     strengths?: string|null,
     *     improvements?: string|null,
     *     comments?: string|null,
     *     responses?: array<int, array{question_id?: int, question_text?: string, rating?: float|null, response_text?: string|null}>
     * }  $data
     * @param  int  $userId
     */
    public function submitReview(AppraisalReviewer $reviewer, array $data, int $userId): AppraisalReviewer
    {
        if (!$reviewer->canSubmit()) {
            throw new InvalidArgumentException(
                "Reviewer cannot submit: current status is '{$reviewer->status}'."
            );
        }

        return DB::transaction(function () use ($reviewer, $data): AppraisalReviewer {
            // Transition status to in_progress if still pending.
            if ($reviewer->status === AppraisalReviewer::STATUS_PENDING) {
                $reviewer->status = AppraisalReviewer::STATUS_IN_PROGRESS;
            }

            $reviewer->fill([
                'overall_rating' => $data['overall_rating'] ?? null,
                'strengths'      => $data['strengths'] ?? null,
                'improvements'   => $data['improvements'] ?? null,
                'comments'       => $data['comments'] ?? null,
            ]);

            // Persist per-question responses.
            foreach ($data['responses'] ?? [] as $responseData) {
                $questionId   = $responseData['question_id'] ?? null;
                $questionText = $responseData['question_text'] ?? '';

                // Denormalise the question text when a question_id is provided
                // so the response remains readable even if the template changes.
                if ($questionId !== null && empty($questionText)) {
                    $question     = AppraisalTemplateQuestion::find($questionId);
                    $questionText = $question?->question ?? '';
                }

                AppraisalReviewerResponse::create([
                    'appraisal_reviewer_id' => $reviewer->id,
                    'question_id'           => $questionId,
                    'question_text'         => $questionText,
                    'rating'                => $responseData['rating'] ?? null,
                    'response_text'         => $responseData['response_text'] ?? null,
                ]);
            }

            $reviewer->submit();

            return $reviewer->load('responses');
        });
    }

    /**
     * Decline a reviewer's request to provide feedback.
     */
    public function declineReview(AppraisalReviewer $reviewer): AppraisalReviewer
    {
        if ($reviewer->isSubmitted()) {
            throw new InvalidArgumentException('Cannot decline a review that has already been submitted.');
        }

        $reviewer->decline();

        return $reviewer;
    }

    /**
     * Aggregate ratings from all submitted reviewers for an appraisal.
     *
     * Returns an array keyed by reviewer_type with the following shape per type:
     * [
     *   'count'          => int,   // number of submitted reviewers
     *   'average_rating' => float, // average overall_rating (null when none submitted)
     * ]
     * Plus a top-level 'weighted_average' which is a simple mean across all types.
     *
     * @return array{
     *   by_type: array<string, array{count: int, average_rating: float|null}>,
     *   weighted_average: float|null,
     *   total_submitted: int
     * }
     */
    public function getAggregatedRatings(PerformanceAppraisal $appraisal): array
    {
        $submitted = AppraisalReviewer::forAppraisal($appraisal->id)
            ->submitted()
            ->whereNotNull('overall_rating')
            ->get();

        $byType = [];

        foreach (AppraisalReviewer::TYPES as $type) {
            $typeReviewers = $submitted->filter(fn ($r) => $r->reviewer_type === $type);
            $count         = $typeReviewers->count();

            $byType[$type] = [
                'count'          => $count,
                'average_rating' => $count > 0
                    ? round($typeReviewers->avg('overall_rating'), 2)
                    : null,
            ];
        }

        $totalSubmitted = $submitted->count();
        $weightedAvg    = $totalSubmitted > 0
            ? round($submitted->avg('overall_rating'), 2)
            : null;

        return [
            'by_type'          => $byType,
            'weighted_average' => $weightedAvg,
            'total_submitted'  => $totalSubmitted,
        ];
    }

    /**
     * Return a completion status summary for an appraisal's 360° review cycle.
     *
     * @return array<string, array{submitted: int, total: int, pending: int, declined: int}>
     */
    public function getCompletionStatus(PerformanceAppraisal $appraisal): array
    {
        $all = AppraisalReviewer::forAppraisal($appraisal->id)->get();

        $status = [];

        foreach (AppraisalReviewer::TYPES as $type) {
            $typeReviewers = $all->filter(fn ($r) => $r->reviewer_type === $type);
            $total         = $typeReviewers->count();

            if ($total === 0) {
                continue;
            }

            $status[$type] = [
                'total'     => $total,
                'submitted' => $typeReviewers->where('status', AppraisalReviewer::STATUS_SUBMITTED)->count(),
                'pending'   => $typeReviewers->whereIn('status', [
                    AppraisalReviewer::STATUS_PENDING,
                    AppraisalReviewer::STATUS_IN_PROGRESS,
                ])->count(),
                'declined'  => $typeReviewers->where('status', AppraisalReviewer::STATUS_DECLINED)->count(),
            ];
        }

        return $status;
    }
}
