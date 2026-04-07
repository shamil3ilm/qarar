<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageTemplate;
use App\Services\Messaging\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function __construct(
        private MessageService $messageService
    ) {}

    /**
     * List message templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MessageTemplate::query()
            ->when($request->channel_type, fn($q, $type) => $q->forChannel($type))
            ->when($request->category, fn($q, $cat) => $q->forCategory($cat))
            ->when($request->language, fn($q, $lang) => $q->forLanguage($lang))
            ->when($request->code, fn($q, $code) => $q->forCode($code))
            ->when($request->is_active !== null, function ($q) use ($request) {
                return $request->is_active === 'true' ? $q->active() : $q->where('is_active', false);
            })
            ->when($request->is_system !== null, function ($q) use ($request) {
                return $request->is_system === 'true' ? $q->system() : $q->custom();
            })
            ->when($request->search, fn($q, $search) => $q->search($search))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'type', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $templates = $query->paginate((int) ($request->per_page ?? 15));

        return $this->paginated($templates);
    }

    /**
     * Store a new message template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'channel_type' => 'required|in:email,sms,whatsapp,push_notification',
            'category' => 'required|in:transactional,promotional,reminder,notification',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'html_body' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'attachments_config' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'parent_template_id' => 'nullable|exists:message_templates,id',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->messageService->createTemplate($validated);

        return $this->created($template);
    }

    /**
     * Show a specific message template.
     */
    public function show(MessageTemplate $messageTemplate): JsonResponse
    {
        return $this->success(
            $messageTemplate->load(['parentTemplate', 'translations', 'channelApprovals'])
        );
    }

    /**
     * Update a message template.
     */
    public function update(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        if ($messageTemplate->isSystem()) {
            return $this->error('System templates cannot be modified.', 'SYSTEM_TEMPLATE', 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
            'channel_type' => 'sometimes|in:email,sms,whatsapp,push_notification',
            'category' => 'sometimes|in:transactional,promotional,reminder,notification',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'html_body' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'attachments_config' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'is_active' => 'nullable|boolean',
        ]);

        $messageTemplate->update($validated);

        return $this->success($messageTemplate->fresh(), 'Message template updated successfully.');
    }

    /**
     * Delete a message template.
     */
    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        if ($messageTemplate->isSystem()) {
            return $this->error('System templates cannot be deleted.', 'SYSTEM_TEMPLATE', 422);
        }

        $messageTemplate->channelApprovals()->delete();
        $messageTemplate->delete();

        return $this->success(null, 'Message template deleted successfully.');
    }

    /**
     * Preview a rendered template with sample data.
     */
    public function preview(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->generateSampleData($messageTemplate);
        $rendered = $this->messageService->renderTemplate($messageTemplate, $sampleData);

        return $this->success([
            'template_id' => $messageTemplate->uuid,
            'channel_type' => $messageTemplate->channel_type,
            'sample_data' => $sampleData,
            'rendered' => $rendered,
        ], 'Template preview generated successfully.');
    }

    /**
     * Render a template with provided data (for actual use).
     */
    public function render(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $rendered = $this->messageService->renderTemplate($messageTemplate, $validated['data']);

        return $this->success($rendered, 'Template rendered successfully.');
    }

    /**
     * Generate sample data for template preview.
     */
    protected function generateSampleData(MessageTemplate $template): array
    {
        $variables = $template->getAvailableVariables();
        $sampleData = [];

        foreach ($variables as $variable) {
            $sampleData[$variable] = "[{$variable}]";
        }

        return $sampleData;
    }
}
