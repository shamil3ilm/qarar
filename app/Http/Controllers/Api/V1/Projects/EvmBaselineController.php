<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\EvmBaseline;
use App\Models\Projects\Project;
use App\Services\Projects\EvmBaselineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvmBaselineController extends Controller
{
    public function __construct(private readonly EvmBaselineService $service) {}

    /**
     * GET /projects/{project}/baselines
     */
    public function index(Project $project): JsonResponse
    {
        $baselines = $this->service->getBaselines($project->id);

        return $this->successResponse($baselines, 'Baselines retrieved');
    }

    /**
     * POST /projects/{project}/baselines
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'baseline_type' => ['required', 'in:original,revised,current'],
            'set_as_active' => ['boolean'],
            'notes'         => ['nullable', 'string'],
        ]);

        $baseline = $this->service->capture(
            project:     $project,
            name:        $data['name'],
            baselineType: $data['baseline_type'],
            setAsActive: (bool) ($data['set_as_active'] ?? false),
            createdBy:   $request->user(),
            notes:       $data['notes'] ?? null,
        );

        return $this->successResponse($baseline, 'Baseline captured', 201);
    }

    /**
     * GET /baselines/{baseline}
     */
    public function show(EvmBaseline $baseline): JsonResponse
    {
        return $this->successResponse($baseline->load('lines'), 'Baseline retrieved');
    }

    /**
     * POST /baselines/{baseline}/approve
     */
    public function approve(Request $request, EvmBaseline $baseline): JsonResponse
    {
        $baseline = $this->service->approve($baseline, $request->user());

        return $this->successResponse($baseline, 'Baseline approved');
    }

    /**
     * POST /baselines/{baseline}/activate
     */
    public function activate(EvmBaseline $baseline): JsonResponse
    {
        $baseline = $this->service->activate($baseline);

        return $this->successResponse($baseline, 'Baseline activated');
    }

    /**
     * GET /projects/{project}/baselines/compare
     */
    public function compare(Project $project): JsonResponse
    {
        $result = $this->service->compareToBaseline($project);

        return $this->successResponse($result, 'Baseline comparison retrieved');
    }
}
