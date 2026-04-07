<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\ProjectTemplate;
use App\Services\Projects\ProjectTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTemplateController extends Controller
{
    public function __construct(private readonly ProjectTemplateService $service) {}

    public function index(Request $request): JsonResponse
    {
        $templates = ProjectTemplate::where('organization_id', $request->user()->organization_id)
            ->orderBy('template_name')
            ->paginate(20);

        return $this->paginated($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_name' => 'required|string|max:255',
            'description'   => 'nullable|string',
            'project_type'  => 'required|in:customer,internal,overhead,capital,maintenance',
            'industry'      => 'nullable|string|max:100',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by']      = $request->user()->id;

        $template = $this->service->createTemplate($data);

        return $this->created($template);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $template = ProjectTemplate::where('organization_id', $request->user()->organization_id)
            ->with(['wbsElements', 'milestones'])
            ->findOrFail($id);

        return $this->success($template);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = ProjectTemplate::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $data     = $request->validate([
            'template_name' => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'project_type'  => 'sometimes|in:customer,internal,overhead,capital,maintenance',
            'industry'      => 'nullable|string|max:100',
            'active'        => 'sometimes|boolean',
        ]);

        $template->update($data);

        return $this->success($template);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $template = ProjectTemplate::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $template->delete();

        return $this->success(null, 'Template deleted');
    }

    public function tree(Request $request, int $id): JsonResponse
    {
        $template = ProjectTemplate::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $tree     = $this->service->getTemplateTree($template);

        return $this->success(['tree' => $tree]);
    }

    public function createProject(Request $request, int $id): JsonResponse
    {
        $template = ProjectTemplate::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $data     = $request->validate([
            'project_name'  => 'required|string|max:255',
            'start_date'    => 'required|date',
        ]);

        $result = $this->service->createProjectFromTemplate($template, $data);

        return $this->success($result, 'Project structure generated from template');
    }
}
