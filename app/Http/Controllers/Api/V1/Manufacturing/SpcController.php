<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\SpcChart;
use App\Models\Manufacturing\SpcSubgroup;
use App\Services\Manufacturing\SpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpcController extends Controller
{
    public function __construct(
        private SpcService $spcService
    ) {}

    /**
     * Calculate Xbar-R control chart statistics for the provided sample data.
     *
     * POST /manufacturing/spc/xbar-r
     *
     * Body:
     *   samples: [[float, ...], ...]  — array of subgroups, each subgroup same size (2–10)
     */
    public function calculateXbarR(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'samples'             => 'required|array|min:2',
            'samples.*'           => 'required|array|min:2|max:10',
            'samples.*.*'         => 'required|numeric',
        ]);

        $result = $this->spcService->calculateXbarR($validated['samples']);

        return $this->success($result, 'Xbar-R chart calculated.');
    }

    /**
     * Calculate process capability indices (Cp, Cpk) for individual measurements.
     *
     * POST /manufacturing/spc/cpk
     *
     * Body:
     *   measurements: [float, ...]
     *   usl: float  — upper specification limit
     *   lsl: float  — lower specification limit
     */
    public function calculateCpk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'measurements'   => 'required|array|min:2',
            'measurements.*' => 'required|numeric',
            'usl'            => 'required|numeric',
            'lsl'            => 'required|numeric|lt:usl',
        ]);

        $result = $this->spcService->calculateCpk(
            array_map('floatval', $validated['measurements']),
            (float) $validated['usl'],
            (float) $validated['lsl']
        );

        return $this->success($result, 'Process capability calculated.');
    }

    /**
     * Create a persistent SPC chart with control limits derived from initial measurements.
     *
     * POST /manufacturing/spc/charts
     */
    public function createChart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'characteristic_name'  => 'required|string|max:100',
            'chart_type'           => 'nullable|in:xbar_r,individual_mr',
            'subgroup_size'        => 'required|integer|min:2|max:10',
            'initial_measurements' => 'required|array|min:4',
            'initial_measurements.*' => 'required|numeric',
            'product_id'           => 'nullable|integer',
            'usl'                  => 'nullable|numeric',
            'lsl'                  => 'nullable|numeric|lt:usl',
        ]);

        $chart = $this->spcService->createChart($request->user()->organization_id, $validated);

        return $this->created($chart, 'SPC chart created.');
    }

    /**
     * Record a new subgroup of measurements for an existing SPC chart.
     *
     * POST /manufacturing/spc/charts/{chartId}/subgroups
     */
    public function recordSubgroup(Request $request, int $chartId): JsonResponse
    {
        $validated = $request->validate([
            'measurements'   => 'required|array|min:1',
            'measurements.*' => 'required|numeric',
        ]);

        $subgroup = $this->spcService->recordSubgroup(
            $chartId,
            array_map('floatval', $validated['measurements']),
            $request->user()->id
        );

        $status  = $subgroup->out_of_control ? 'OUT_OF_CONTROL' : 'IN_CONTROL';
        $message = "Subgroup recorded: {$status}";

        return $this->created($subgroup, $message);
    }

    /**
     * Get the trend view (last N subgroups) for an SPC chart.
     *
     * GET /manufacturing/spc/charts/{chartId}/trend
     */
    public function trend(Request $request, int $chartId): JsonResponse
    {
        $limit = max(1, min(200, $request->integer('limit', 30)));

        return $this->success($this->spcService->getTrend($chartId, $limit));
    }

    /**
     * Generate control chart data from an inspection lot's measurement results.
     *
     * GET /manufacturing/spc/inspection-lot/{id}/chart?characteristic=Diameter
     */
    public function inspectionLotChart(Request $request, InspectionLot $inspectionLot): JsonResponse
    {
        $validated = $request->validate([
            'characteristic' => 'required|string|max:200',
        ]);

        $chartData = $this->spcService->generateControlChart(
            $inspectionLot,
            $validated['characteristic']
        );

        return $this->success($chartData, 'Control chart generated.');
    }
}
