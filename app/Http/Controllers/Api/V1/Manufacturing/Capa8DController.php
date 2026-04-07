<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CapaEightD;
use App\Services\Manufacturing\CapaEightDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Capa8DController extends Controller
{
    public function __construct(
        private readonly CapaEightDService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->service->list(
            $request->user()->organization_id,
            $request->only(['status', 'source_type', 'per_page']),
        );

        return $this->paginated($records);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => 'required|string|max:200',
            'source_complaint_id' => 'nullable|integer',
            'source_type'         => 'nullable|string|max:50|in:complaint,nonconformance,audit_finding',
            // D0 optional on creation
            'd0_emergency_response' => 'nullable|string',
            'd0_date'               => 'nullable|date',
        ]);

        $record = $this->service->create(
            $request->user()->organization_id,
            $data,
            $request->user()->id,
        );

        return $this->created($record);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $record = $this->service->find(
            $request->user()->organization_id,
            $uuid,
        );

        return $this->success($record);
    }

    /**
     * Update a specific discipline (d0-d8) for an 8D record.
     */
    public function updateStep(Request $request, string $uuid, string $step): JsonResponse
    {
        $validSteps = array_keys([
            'd0' => true, 'd1' => true, 'd2' => true,
            'd3' => true, 'd4' => true, 'd5' => true,
            'd6' => true, 'd7' => true, 'd8' => true,
        ]);

        $data = $request->validate([
            // D0
            'd0_emergency_response' => 'nullable|string',
            'd0_date'               => 'nullable|date',
            // D1
            'd1_team_members'  => 'nullable|array',
            'd1_team_members.*' => 'string',
            'd1_champion_id'   => 'nullable|integer|exists:users,id',
            // D2
            'd2_problem_description' => 'nullable|string',
            'd2_is_is_not'           => 'nullable|string',
            // D3
            'd3_containment_actions'  => 'nullable|string',
            'd3_implemented_date'     => 'nullable|date',
            'd3_verified'             => 'boolean',
            // D4
            'd4_root_cause'   => 'nullable|string',
            'd4_escape_point' => 'nullable|string',
            // D5
            'd5_corrective_actions' => 'nullable|string',
            // D6
            'd6_implementation_plan' => 'nullable|string',
            'd6_target_date'         => 'nullable|date',
            'd6_completed_date'      => 'nullable|date',
            'd6_verified'            => 'boolean',
            // D7
            'd7_systemic_preventions' => 'nullable|string',
            'd7_lessons_learned'      => 'nullable|string',
            // D8
            'd8_recognition'  => 'nullable|string',
            'd8_closure_date' => 'nullable|date',
        ]);

        if (!in_array($step, $validSteps, true)) {
            return $this->error("Invalid step '{$step}'. Valid steps: d0–d8.", 422);
        }

        $record = $this->service->updateStep(
            $request->user()->organization_id,
            $uuid,
            $step,
            $data,
        );

        return $this->success($record);
    }

    /**
     * Close the 8D — advances status to d8_closed.
     */
    public function close(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'd8_recognition'  => 'nullable|string',
            'd8_closure_date' => 'nullable|date',
        ]);

        $record = $this->service->close(
            $request->user()->organization_id,
            $uuid,
            $data,
        );

        return $this->success($record);
    }
}
