<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectTemplate;
use App\Models\Projects\ProjectTemplateWbs;
use Illuminate\Support\Str;

class ProjectTemplateService
{
    public function createTemplate(array $data): ProjectTemplate
    {
        return ProjectTemplate::create([
            'uuid'          => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'template_name' => $data['template_name'],
            'description'   => $data['description'] ?? null,
            'project_type'  => $data['project_type'],
            'industry'      => $data['industry'] ?? null,
            'active'        => $data['active'] ?? true,
            'created_by'    => $data['created_by'],
        ]);
    }

    public function getTemplateTree(ProjectTemplate $template): array
    {
        $wbsElements = $template->wbsElements()->with('children')->whereNull('parent_id')->get();
        return $this->buildTree($wbsElements);
    }

    private function buildTree($elements): array
    {
        $tree = [];
        foreach ($elements as $element) {
            $node = $element->toArray();
            $node['children'] = $this->buildTree($element->children);
            $tree[] = $node;
        }
        return $tree;
    }

    public function createProjectFromTemplate(ProjectTemplate $template, array $projectData): array
    {
        $wbsElements = $template->wbsElements()->get();
        $milestones  = $template->milestones()->get();

        return [
            'project_type'     => $template->project_type,
            'wbs_elements'     => $wbsElements->toArray(),
            'milestones'       => $milestones->toArray(),
            'template_used_id' => $template->id,
        ];
    }
}
