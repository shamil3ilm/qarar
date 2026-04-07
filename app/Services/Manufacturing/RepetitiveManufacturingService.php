<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\RepetitiveMfgBackflush;
use App\Models\Manufacturing\RepetitiveMfgSchedule;
use App\Models\Manufacturing\RepetitiveMfgScheduleLine;
use App\Services\Inventory\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepetitiveManufacturingService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * Create a new repetitive manufacturing schedule along with
     * one schedule line per calendar day in the planned range.
     */
    public function createSchedule(array $data): RepetitiveMfgSchedule
    {
        return DB::transaction(function () use ($data): RepetitiveMfgSchedule {
            $schedule = RepetitiveMfgSchedule::create([
                'organization_id'          => auth()->user()->organization_id,
                'product_id'               => $data['product_id'],
                'production_version_id'    => $data['production_version_id'] ?? null,
                'production_line_id'       => $data['production_line_id'],
                'schedule_date_from'       => $data['schedule_date_from'],
                'schedule_date_to'         => $data['schedule_date_to'],
                'total_planned_quantity'   => $data['total_planned_quantity'],
                'total_confirmed_quantity' => 0,
                'status'                   => RepetitiveMfgSchedule::STATUS_PLANNED,
                'created_by'               => auth()->id(),
            ]);

            $this->generateScheduleLines($schedule, (float) $data['total_planned_quantity']);

            return $schedule->load(['lines', 'productionLine', 'product']);
        });
    }

    /**
     * Confirm a quantity against a schedule line, updating line and header totals.
     */
    public function confirmScheduleLine(RepetitiveMfgScheduleLine $line, float $quantity): void
    {
        DB::transaction(function () use ($line, $quantity): void {
            $newConfirmed = (float) $line->confirmed_quantity + $quantity;

            $status = match (true) {
                $newConfirmed >= (float) $line->planned_quantity => RepetitiveMfgScheduleLine::STATUS_CONFIRMED,
                $newConfirmed > 0                                => RepetitiveMfgScheduleLine::STATUS_PARTIAL,
                default                                          => RepetitiveMfgScheduleLine::STATUS_PLANNED,
            };

            $line->update([
                'confirmed_quantity' => $newConfirmed,
                'status'             => $status,
            ]);

            // Roll up confirmed total on the parent schedule
            $schedule = $line->schedule;
            $schedule->increment('total_confirmed_quantity', $quantity);
            $schedule->refresh();

            $this->updateScheduleStatus($schedule);
        });
    }

    /**
     * Record a backflush (production confirmation) against a schedule.
     * Creates stock movements for consumed components via StockService.
     */
    public function performBackflush(array $data): RepetitiveMfgBackflush
    {
        return DB::transaction(function () use ($data): RepetitiveMfgBackflush {
            $schedule = RepetitiveMfgSchedule::findOrFail($data['repetitive_mfg_schedule_id']);

            $backflush = RepetitiveMfgBackflush::create([
                'organization_id'             => auth()->user()->organization_id,
                'repetitive_mfg_schedule_id'  => $schedule->id,
                'backflush_date'              => $data['backflush_date'] ?? now(),
                'quantity_produced'           => $data['quantity_produced'],
                'quantity_scrapped'           => $data['quantity_scrapped'] ?? 0,
                'component_movements'         => $data['component_movements'] ?? null,
                'labor_time_minutes'          => $data['labor_time_minutes'] ?? null,
                'created_by'                  => auth()->id(),
            ]);

            // Record component stock movements when movements data is provided
            if (!empty($data['component_movements'])) {
                foreach ($data['component_movements'] as $movement) {
                    $this->stockService->issueStock([
                        'product_id'      => $movement['product_id'],
                        'quantity'        => $movement['quantity'],
                        'warehouse_id'    => $movement['warehouse_id'] ?? null,
                        'reference_type'  => RepetitiveMfgBackflush::class,
                        'reference_id'    => $backflush->id,
                    ]);
                }
            }

            return $backflush;
        });
    }

    /**
     * Return production progress summary for a schedule.
     *
     * @return array{
     *   total_planned: float,
     *   total_confirmed: float,
     *   total_produced: float,
     *   total_scrapped: float,
     *   progress_percent: float,
     *   lines: array<int, array<string, mixed>>
     * }
     */
    public function getProductionProgress(int $scheduleId): array
    {
        $schedule = RepetitiveMfgSchedule::with(['lines', 'backflushes'])->findOrFail($scheduleId);

        $totalProduced = $schedule->backflushes->sum('quantity_produced');
        $totalScrapped = $schedule->backflushes->sum('quantity_scrapped');
        $planned       = (float) $schedule->total_planned_quantity;

        return [
            'total_planned'    => $planned,
            'total_confirmed'  => (float) $schedule->total_confirmed_quantity,
            'total_produced'   => (float) $totalProduced,
            'total_scrapped'   => (float) $totalScrapped,
            'progress_percent' => $planned > 0 ? round(($totalProduced / $planned) * 100, 2) : 0.0,
            'lines'            => $schedule->lines->map(fn($l) => [
                'id'                 => $l->id,
                'schedule_date'      => $l->schedule_date->toDateString(),
                'planned_quantity'   => (float) $l->planned_quantity,
                'confirmed_quantity' => (float) $l->confirmed_quantity,
                'status'             => $l->status,
            ])->all(),
        ];
    }

    /**
     * Calculate utilization for a production line within a date range.
     *
     * @return array{
     *   line_id: int,
     *   date_from: string,
     *   date_to: string,
     *   total_planned: float,
     *   total_confirmed: float,
     *   utilization_percent: float
     * }
     */
    public function getLineUtilization(int $lineId, string $dateFrom, string $dateTo): array
    {
        $schedules = RepetitiveMfgSchedule::where('production_line_id', $lineId)
            ->where('schedule_date_from', '<=', $dateTo)
            ->where('schedule_date_to', '>=', $dateFrom)
            ->get();

        $totalPlanned   = $schedules->sum('total_planned_quantity');
        $totalConfirmed = $schedules->sum('total_confirmed_quantity');

        return [
            'line_id'             => $lineId,
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'total_planned'       => (float) $totalPlanned,
            'total_confirmed'     => (float) $totalConfirmed,
            'utilization_percent' => $totalPlanned > 0
                ? round(($totalConfirmed / $totalPlanned) * 100, 2)
                : 0.0,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateScheduleLines(RepetitiveMfgSchedule $schedule, float $totalQty): void
    {
        $from    = Carbon::parse($schedule->schedule_date_from);
        $to      = Carbon::parse($schedule->schedule_date_to);
        $days    = max(1, $from->diffInDays($to) + 1);
        $perDay  = round($totalQty / $days, 4);

        $current = $from->copy();
        while ($current->lte($to)) {
            RepetitiveMfgScheduleLine::create([
                'repetitive_mfg_schedule_id' => $schedule->id,
                'schedule_date'              => $current->toDateString(),
                'planned_quantity'           => $perDay,
                'confirmed_quantity'         => 0,
                'status'                     => RepetitiveMfgScheduleLine::STATUS_PLANNED,
            ]);
            $current->addDay();
        }
    }

    private function updateScheduleStatus(RepetitiveMfgSchedule $schedule): void
    {
        $status = match (true) {
            (float) $schedule->total_confirmed_quantity >= (float) $schedule->total_planned_quantity
                => RepetitiveMfgSchedule::STATUS_COMPLETED,
            (float) $schedule->total_confirmed_quantity > 0
                => RepetitiveMfgSchedule::STATUS_IN_PROGRESS,
            default => RepetitiveMfgSchedule::STATUS_PLANNED,
        };

        $schedule->update(['status' => $status]);
    }
}
