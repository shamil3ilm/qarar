<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PostingValidationRule;
use App\Services\Accounting\DocumentSplittingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostingValidationRuleController extends Controller
{
    public function __construct(
        private readonly DocumentSplittingService $service
    ) {}

    /**
     * GET /posting-validation-rules
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'rule_type'     => ['nullable', 'string', 'in:validation,substitution'],
            'trigger_event' => ['nullable', 'string'],
        ]);

        $query = PostingValidationRule::where('organization_id', $this->organizationId($request))
            ->ordered()
            ->when($request->filled('rule_type'), fn($q) => $q->where('rule_type', $request->rule_type))
            ->when($request->filled('trigger_event'), fn($q) => $q->where('trigger_event', $request->trigger_event));

        return $this->success($query->paginate(50));
    }

    /**
     * POST /posting-validation-rules
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rule_name'     => ['required', 'string', 'max:100'],
            'rule_type'     => ['required', 'string', 'in:validation,substitution'],
            'trigger_event' => ['required', 'string', 'max:50'],
            'conditions'    => ['required', 'array', 'min:1'],
            'conditions.*.field'    => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'actions'       => ['required', 'array', 'min:1'],
            'actions.*.field'       => ['required', 'string'],
            'actions.*.action_type' => ['required', 'string'],
            'is_active'     => ['boolean'],
            'priority'      => ['integer', 'min:1'],
            'error_message' => ['nullable', 'string'],
        ]);

        $data['organization_id'] = $this->organizationId($request);

        $rule = PostingValidationRule::create($data);

        return $this->success($rule, 'Posting validation rule created.', 201);
    }

    /**
     * GET /posting-validation-rules/{rule}
     */
    public function show(PostingValidationRule $postingValidationRule): JsonResponse
    {
        return $this->success($postingValidationRule);
    }

    /**
     * PUT /posting-validation-rules/{rule}
     */
    public function update(Request $request, PostingValidationRule $postingValidationRule): JsonResponse
    {
        $data = $request->validate([
            'rule_name'     => ['sometimes', 'string', 'max:100'],
            'rule_type'     => ['sometimes', 'string', 'in:validation,substitution'],
            'trigger_event' => ['sometimes', 'string', 'max:50'],
            'conditions'    => ['sometimes', 'array', 'min:1'],
            'conditions.*.field'    => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', 'string'],
            'actions'       => ['sometimes', 'array', 'min:1'],
            'actions.*.field'       => ['required_with:actions', 'string'],
            'actions.*.action_type' => ['required_with:actions', 'string'],
            'is_active'     => ['boolean'],
            'priority'      => ['integer', 'min:1'],
            'error_message' => ['nullable', 'string'],
        ]);

        $postingValidationRule->update($data);

        return $this->success($postingValidationRule, 'Posting validation rule updated.');
    }

    /**
     * DELETE /posting-validation-rules/{rule}
     */
    public function destroy(PostingValidationRule $postingValidationRule): JsonResponse
    {
        $postingValidationRule->delete();

        return $this->success(null, 'Posting validation rule deleted.');
    }

    /**
     * POST /posting-validation-rules/evaluate
     *
     * Dry-run evaluation: validate + apply substitutions for the given document
     * payload and return the (possibly modified) data without saving.
     */
    public function evaluate(Request $request): JsonResponse
    {
        $request->validate([
            'document_data'        => ['required', 'array'],
            'document_data.organization_id' => ['nullable', 'integer'],
            'trigger_event'        => ['nullable', 'string'],
        ]);

        $documentData = $request->input('document_data', []);
        $documentData['organization_id'] ??= $this->organizationId($request);
        $event = $request->input('trigger_event', 'on_save');

        $modified = $this->service->evaluatePostingRules($documentData, $event);

        return $this->success([
            'original'  => $documentData,
            'evaluated' => $modified,
        ]);
    }
}
