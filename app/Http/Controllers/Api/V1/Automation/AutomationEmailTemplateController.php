<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Http\Controllers\Controller;
use App\Models\Automation\AutomationEmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationEmailTemplateController extends Controller
{
    /**
     * List email templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AutomationEmailTemplate::query()
            ->when($request->category, fn($q, $category) => $q->forCategory($category))
            ->when($request->is_active !== null, function ($q) use ($request) {
                return $request->is_active === 'true' ? $q->active() : $q->inactive();
            })
            ->when($request->search, fn($q, $search) => $q->search($search))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $templates = $query->paginate((int) ($request->per_page ?? 15));

        return $this->paginated($templates);
    }

    /**
     * Store a new email template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $template = AutomationEmailTemplate::create($validated);

        return $this->created($template);
    }

    /**
     * Show a specific email template.
     */
    public function show(AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        return $this->success($automationEmailTemplate);
    }

    /**
     * Update an email template.
     */
    public function update(Request $request, AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $automationEmailTemplate->update($validated);

        return $this->success($automationEmailTemplate->fresh(), 'Email template updated successfully.');
    }

    /**
     * Delete an email template.
     */
    public function destroy(AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $automationEmailTemplate->delete();

        return $this->success(null, 'Email template deleted successfully.');
    }

    /**
     * Preview rendered email template with sample data.
     */
    public function preview(Request $request, AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->generateSampleData($automationEmailTemplate);
        $rendered = $automationEmailTemplate->render($sampleData);

        return $this->success([
            'template_id' => $automationEmailTemplate->id,
            'sample_data' => $sampleData,
            'rendered' => $rendered,
        ], 'Template preview generated successfully.');
    }

    /**
     * Generate sample data for template preview.
     */
    protected function generateSampleData(AutomationEmailTemplate $template): array
    {
        $variables = $template->getAvailableVariables();
        $sampleData = [];

        foreach ($variables as $variable) {
            $sampleData[$variable] = "{{$variable}_sample}";
        }

        return $sampleData;
    }
}
