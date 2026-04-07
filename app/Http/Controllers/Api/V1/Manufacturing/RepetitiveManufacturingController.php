<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionLine;
use App\Models\Manufacturing\RepetitiveMfgSchedule;
use App\Models\Manufacturing\RepetitiveMfgScheduleLine;
use App\Services\Manufacturing\RepetitiveManufacturingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepetitiveManufacturingController extends Controller
{
    public function __construct(
        private readonly RepetitiveManufacturingService $service,
    ) {}

    // ── Production Lines ──────────────────────────────────────────────────────

    /**
     * List production lines.
     */
    public function lines(Request $request): JsonResponse
    {
        $query = ProductionLine::with(['workCenter', 'unit'])
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('code');

        $lines = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($lines);
    }

    /**
     * Create a production line.
     */
    public function storeLine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'              => 'required|string|max:30',
            'name'              => 'required|string|max:255',
            'work_center_id'    => ['nullable', Rule::exists('work_centers', 'id')->where('organization_id', auth()->user()->organization_id)],
            'capacity_per_hour' => 'nullable|numeric|min:0',
            'unit_id'           => 'nullable|exists:units_of_measure,id',
            'is_active'         => 'nullable|boolean',
        ]);

        $line = ProductionLine::create($validated);

        return $this->created($line->load(['workCenter', 'unit']));
    }

    // ── Schedules ─────────────────────────────────────────────────────────────

    /**
     * List repetitive manufacturing schedules.
     */
    public function schedules(Request $request): JsonResponse
    {
        $query = RepetitiveMfgSchedule::with(['product', 'productionLine', 'productionVersion'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->product_id, fn($q, $v) => $q->where('product_id', $v))
            ->when($request->line_id, fn($q, $v) => $q->where('production_line_id', $v))
            ->orderByDesc('schedule_date_from');

        $schedules = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($schedules);
    }

    /**
     * Create a new repetitive manufacturing schedule.
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'             => ['required', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'production_version_id'  => 'nullable|exists:production_versions,id',
            'production_line_id'     => ['required', Rule::exists('production_lines', 'id')->where('organization_id', auth()->user()->organization_id)],
            'schedule_date_from'     => 'required|date',
            'schedule_date_to'       => 'required|date|after_or_equal:schedule_date_from',
            'total_planned_quantity' => 'required|numeric|min:0.0001',
        ]);

        $schedule = $this->service->createSchedule($validated);

        return $this->created($schedule);
    }

    /**
     * Show a schedule with its lines.
     */
    public function showSchedule(int $id): JsonResponse
    {
        $schedule = RepetitiveMfgSchedule::with(['product', 'productionLine', 'productionVersion', 'lines', 'backflushes'])
            ->find($id);

        if ($schedule === null) {
            return $this->notFound('Schedule not found.');
        }

        return $this->success($schedule);
    }

    /**
     * Confirm a quantity against a schedule line.
     */
    public function confirmLine(Request $request, int $lineId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $line = RepetitiveMfgScheduleLine::find($lineId);

        if ($line === null) {
            return $this->notFound('Schedule line not found.');
        }

        $this->service->confirmScheduleLine($line, (float) $validated['quantity']);

        return $this->success($line->fresh(), 'Schedule line confirmed.');
    }

    /**
     * Perform a backflush (production confirmation).
     */
    public function backflush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repetitive_mfg_schedule_id' => ['required', Rule::exists('repetitive_mfg_schedules', 'id')->where('organization_id', auth()->user()->organization_id)],
            'backflush_date'             => 'nullable|date',
            'quantity_produced'          => 'required|numeric|min:0.0001',
            'quantity_scrapped'          => 'nullable|numeric|min:0',
            'component_movements'        => 'nullable|array',
            'component_movements.*.product_id'   => 'required_with:component_movements|integer|exists:products,id',
            'component_movements.*.quantity'     => 'required_with:component_movements|numeric|min:0.0001',
            'component_movements.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'labor_time_minutes'         => 'nullable|numeric|min:0',
        ]);

        $backflush = $this->service->performBackflush($validated);

        return $this->created($backflush, 'Backflush recorded successfully.');
    }

    /**
     * Get production progress for a schedule.
     */
    public function progress(int $id): JsonResponse
    {
        $schedule = RepetitiveMfgSchedule::find($id);

        if ($schedule === null) {
            return $this->notFound('Schedule not found.');
        }

        $progress = $this->service->getProductionProgress($id);

        return $this->success($progress);
    }
}
