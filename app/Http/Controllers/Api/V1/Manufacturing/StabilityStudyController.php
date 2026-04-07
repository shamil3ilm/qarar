<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\StabilityStudy;
use App\Models\Manufacturing\StabilityStudyTimePoint;
use App\Services\Manufacturing\StabilityStudyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StabilityStudyController extends Controller
{
    public function __construct(private readonly StabilityStudyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $studies = $this->service->list($request->user()->organization_id, $request->query());
        return $this->success($studies, 'Stability studies retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'study_number'       => 'required|string|max:50',
            'product_id'         => 'required|integer|exists:products,id',
            'inventory_batch_id' => 'nullable|integer|exists:inventory_batches,id',
            'study_type'         => 'required|in:real_time,accelerated,intermediate',
            'start_date'         => 'required|date',
            'planned_end_date'   => 'nullable|date|after_or_equal:start_date',
            'storage_condition'  => 'nullable|string|max:100',
            'protocol_reference' => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
        ]);

        $study = $this->service->create($request->user()->organization_id, $data);
        return $this->created($study, 'Stability study created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $study = StabilityStudy::where('organization_id', $request->user()->organization_id)
            ->with(['product', 'inventoryBatch', 'timePoints.results'])
            ->findOrFail($id);
        return $this->success($study, 'Study retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $study = StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'study_number'       => 'string|max:50',
            'study_type'         => 'in:real_time,accelerated,intermediate',
            'start_date'         => 'date',
            'planned_end_date'   => 'nullable|date',
            'storage_condition'  => 'nullable|string|max:100',
            'protocol_reference' => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
        ]);

        try {
            $updated = $this->service->update($study, $data);
            return $this->success($updated, 'Study updated.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $study = StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->activate($study);
            return $this->success($updated, 'Study activated.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $study = StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        try {
            $updated = $this->service->complete($study);
            return $this->success($updated, 'Study completed.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }

    public function summary(Request $request, int $id): JsonResponse
    {
        $summary = $this->service->getStudySummary($id, $request->user()->organization_id);
        return $this->success($summary, 'Study summary retrieved.');
    }

    public function addTimePoint(Request $request, int $id): JsonResponse
    {
        $study = StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'time_point'     => 'required|string|max:20',
            'scheduled_date' => 'required|date',
        ]);

        $timePoint = $this->service->addTimePoint($study, $data);
        return $this->created($timePoint, 'Time point added.');
    }

    public function updateTimePoint(Request $request, int $id, int $tpId): JsonResponse
    {
        StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $timePoint = StabilityStudyTimePoint::where('stability_study_id', $id)->findOrFail($tpId);

        $data = $request->validate([
            'time_point'     => 'string|max:20',
            'scheduled_date' => 'date',
            'actual_date'    => 'nullable|date',
            'status'         => 'in:scheduled,in_progress,completed,missed',
        ]);

        $updated = $this->service->updateTimePoint($timePoint, $data);
        return $this->success($updated, 'Time point updated.');
    }

    public function addResult(Request $request, int $id, int $tpId): JsonResponse
    {
        StabilityStudy::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $timePoint = StabilityStudyTimePoint::where('stability_study_id', $id)->findOrFail($tpId);

        $data = $request->validate([
            'parameter_name'    => 'required|string|max:100',
            'specification_min' => 'nullable|numeric',
            'specification_max' => 'nullable|numeric',
            'result_value'      => 'nullable|numeric',
            'result_text'       => 'nullable|string|max:255',
            'unit_of_measure'   => 'nullable|string|max:20',
            'is_pass'           => 'nullable|boolean',
            'tested_by'         => 'nullable|integer|exists:users,id',
        ]);

        $result = $this->service->addResult($timePoint, $data);
        return $this->created($result, 'Result added.');
    }
}
