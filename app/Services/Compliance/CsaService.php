<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\GrcCsaQuestion;
use App\Models\Compliance\GrcCsaQuestionnaire;
use App\Models\Compliance\GrcCsaResponse;
use Illuminate\Support\Facades\DB;

class CsaService
{
    /**
     * Create a new questionnaire with its questions.
     *
     * @param array{
     *   title: string,
     *   description?: string,
     *   control_area: string,
     *   due_date: string,
     *   owner_id: int,
     *   questions?: array<int, array{question_text: string, response_type?: string, sort_order?: int, guidance?: string, is_required?: bool, control_objective?: string}>,
     * } $data
     */
    public function createQuestionnaire(int $organizationId, array $data, int $userId): GrcCsaQuestionnaire
    {
        return DB::transaction(function () use ($organizationId, $data, $userId): GrcCsaQuestionnaire {
            $number = $this->generateQuestionnaireNumber($organizationId);

            $questionnaire = GrcCsaQuestionnaire::create([
                'organization_id'       => $organizationId,
                'questionnaire_number'  => $number,
                'title'                 => $data['title'],
                'description'           => $data['description'] ?? null,
                'control_area'          => $data['control_area'],
                'due_date'              => $data['due_date'],
                'status'                => GrcCsaQuestionnaire::STATUS_DRAFT,
                'owner_id'              => $data['owner_id'],
                'created_by'            => $userId,
            ]);

            if (!empty($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $index => $questionData) {
                    GrcCsaQuestion::create([
                        'questionnaire_id'  => $questionnaire->id,
                        'sort_order'        => $questionData['sort_order'] ?? ($index + 1),
                        'question_text'     => $questionData['question_text'],
                        'guidance'          => $questionData['guidance'] ?? null,
                        'response_type'     => $questionData['response_type'] ?? GrcCsaQuestion::RESPONSE_YES_NO,
                        'is_required'       => $questionData['is_required'] ?? true,
                        'control_objective' => $questionData['control_objective'] ?? null,
                    ]);
                }
            }

            return $questionnaire->load('questions');
        });
    }

    public function publishQuestionnaire(int $organizationId, string $uuid): GrcCsaQuestionnaire
    {
        $questionnaire = GrcCsaQuestionnaire::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $questionnaire->update(['status' => GrcCsaQuestionnaire::STATUS_PUBLISHED]);

        return $questionnaire->fresh('questions');
    }

    /**
     * Record responses for a published questionnaire.
     *
     * @param array<int, array{question_id: int, response_value?: string, comments?: string}> $responses
     * @return array{recorded: int, all_required_answered: bool}
     */
    public function recordResponse(int $organizationId, string $uuid, int $userId, array $responses): array
    {
        $questionnaire = GrcCsaQuestionnaire::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return DB::transaction(function () use ($questionnaire, $userId, $responses): array {
            $recorded = 0;

            foreach ($responses as $responseData) {
                GrcCsaResponse::updateOrCreate(
                    [
                        'questionnaire_id' => $questionnaire->id,
                        'question_id'      => $responseData['question_id'],
                        'respondent_id'    => $userId,
                    ],
                    [
                        'response_value' => $responseData['response_value'] ?? null,
                        'comments'       => $responseData['comments'] ?? null,
                    ]
                );
                $recorded++;
            }

            // Update status to in_progress if still draft/published
            if (in_array($questionnaire->status, [GrcCsaQuestionnaire::STATUS_PUBLISHED, GrcCsaQuestionnaire::STATUS_DRAFT], true)) {
                $questionnaire->update(['status' => GrcCsaQuestionnaire::STATUS_IN_PROGRESS]);
            }

            $allRequiredAnswered = $this->checkAllRequiredAnswered($questionnaire->id, $userId);

            return [
                'recorded'               => $recorded,
                'all_required_answered'  => $allRequiredAnswered,
            ];
        });
    }

    /**
     * Get completion status for a questionnaire.
     *
     * @return array{total_questions: int, required_questions: int, answered: int, unanswered: int, completion_percentage: float, unanswered_questions: array}
     */
    public function getCompletionStatus(int $organizationId, string $uuid): array
    {
        $questionnaire = GrcCsaQuestionnaire::with('questions')
            ->where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $totalQuestions    = $questionnaire->questions->count();
        $requiredQuestions = $questionnaire->questions->where('is_required', true)->count();

        $answeredIds = GrcCsaResponse::where('questionnaire_id', $questionnaire->id)
            ->whereNotNull('response_value')
            ->pluck('question_id')
            ->toArray();

        $unansweredQuestions = $questionnaire->questions
            ->whereNotIn('id', $answeredIds)
            ->values();

        $answeredCount        = count(array_unique($answeredIds));
        $completionPercentage = $totalQuestions > 0
            ? round(($answeredCount / $totalQuestions) * 100, 2)
            : 0.0;

        return [
            'total_questions'       => $totalQuestions,
            'required_questions'    => $requiredQuestions,
            'answered'              => $answeredCount,
            'unanswered'            => $totalQuestions - $answeredCount,
            'completion_percentage' => $completionPercentage,
            'unanswered_questions'  => $unansweredQuestions->toArray(),
        ];
    }

    /**
     * Review a questionnaire — mark each response as effective or not.
     *
     * @param array{
     *   responses?: array<int, array{response_id: int, is_effective: bool, reviewer_notes?: string}>,
     * } $reviewData
     */
    public function reviewQuestionnaire(int $organizationId, string $uuid, array $reviewData, int $userId): GrcCsaQuestionnaire
    {
        $questionnaire = GrcCsaQuestionnaire::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return DB::transaction(function () use ($questionnaire, $reviewData): GrcCsaQuestionnaire {
            if (!empty($reviewData['responses']) && is_array($reviewData['responses'])) {
                foreach ($reviewData['responses'] as $review) {
                    GrcCsaResponse::where('id', $review['response_id'])
                        ->where('questionnaire_id', $questionnaire->id)
                        ->update([
                            'is_effective'   => $review['is_effective'],
                            'reviewer_notes' => $review['reviewer_notes'] ?? null,
                        ]);
                }
            }

            $questionnaire->update(['status' => GrcCsaQuestionnaire::STATUS_REVIEWED]);

            return $questionnaire->fresh(['questions', 'responses']);
        });
    }

    private function checkAllRequiredAnswered(int $questionnaireId, int $userId): bool
    {
        $requiredQuestionIds = GrcCsaQuestion::where('questionnaire_id', $questionnaireId)
            ->where('is_required', true)
            ->pluck('id')
            ->toArray();

        if (empty($requiredQuestionIds)) {
            return true;
        }

        $answeredRequiredIds = GrcCsaResponse::where('questionnaire_id', $questionnaireId)
            ->where('respondent_id', $userId)
            ->whereIn('question_id', $requiredQuestionIds)
            ->whereNotNull('response_value')
            ->pluck('question_id')
            ->toArray();

        return count(array_diff($requiredQuestionIds, $answeredRequiredIds)) === 0;
    }

    private function generateQuestionnaireNumber(int $organizationId): string
    {
        $count = GrcCsaQuestionnaire::where('organization_id', $organizationId)->withTrashed()->count();

        return 'CSA-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
