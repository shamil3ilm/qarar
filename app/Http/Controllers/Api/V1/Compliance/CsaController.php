<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\GrcCsaQuestionnaire;
use App\Services\Compliance\CsaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsaController extends Controller
{
    public function __construct(
        private readonly CsaService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $query = GrcCsaQuestionnaire::with('owner')
            ->where('organization_id', $organizationId)
            ->when($request->has('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->has('control_area'), fn($q) => $q->where('control_area', $request->string('control_area')));

        $questionnaires = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($questionnaires);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                       => ['required', 'string', 'max:200'],
            'description'                 => ['nullable', 'string'],
            'control_area'                => ['required', 'in:financial_reporting,it_general,operational,compliance,fraud_prevention'],
            'due_date'                    => ['required', 'date'],
            'owner_id'                    => ['required', 'integer', 'exists:users,id'],
            'questions'                   => ['nullable', 'array'],
            'questions.*.question_text'   => ['required_with:questions', 'string'],
            'questions.*.response_type'   => ['nullable', 'in:yes_no,rating_1_5,text,date,percentage'],
            'questions.*.guidance'        => ['nullable', 'string'],
            'questions.*.is_required'     => ['nullable', 'boolean'],
            'questions.*.control_objective' => ['nullable', 'string', 'max:200'],
            'questions.*.sort_order'      => ['nullable', 'integer'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $questionnaire = $this->service->createQuestionnaire($organizationId, $data, $userId);

        return $this->created($questionnaire, 'CSA questionnaire created');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $questionnaire = GrcCsaQuestionnaire::with(['owner', 'questions', 'responses.respondent'])
            ->where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->success($questionnaire);
    }

    public function publish(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $questionnaire = $this->service->publishQuestionnaire($organizationId, $uuid);

        return $this->success($questionnaire, 'Questionnaire published');
    }

    public function respond(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'responses'                    => ['required', 'array'],
            'responses.*.question_id'      => ['required', 'integer', 'exists:grc_csa_questions,id'],
            'responses.*.response_value'   => ['nullable', 'string', 'max:500'],
            'responses.*.comments'         => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $result = $this->service->recordResponse($organizationId, $uuid, $userId, $data['responses']);

        return $this->success($result, 'Responses recorded');
    }

    public function completion(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $status = $this->service->getCompletionStatus($organizationId, $uuid);

        return $this->success($status);
    }

    public function review(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'responses'                      => ['nullable', 'array'],
            'responses.*.response_id'        => ['required_with:responses', 'integer', 'exists:grc_csa_responses,id'],
            'responses.*.is_effective'       => ['required_with:responses', 'boolean'],
            'responses.*.reviewer_notes'     => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $questionnaire = $this->service->reviewQuestionnaire($organizationId, $uuid, $data, $userId);

        return $this->success($questionnaire, 'Questionnaire reviewed');
    }
}
