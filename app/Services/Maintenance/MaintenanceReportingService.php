<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceKpi;
use App\Models\Maintenance\MaintenanceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MaintenanceReportingService
{
    /**
     * Compute MTBF, MTTR, availability, downtime for a period and persist as KPI snapshot.
     */
    public function computeKpis(int $organizationId, int $year, int $month): void
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $orders = MaintenanceOrder::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('actual_start', [$from, $to])
            ->whereNotNull('actual_end')
            ->get();

        // Aggregate by equipment
        $byEquipment = $orders->groupBy('equipment_id');

        foreach ($byEquipment as $equipmentId => $eqOrders) {
            $this->upsertEquipmentKpi(
                $organizationId,
                (int) $equipmentId,
                (string) $year,
                str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                $eqOrders
            );
        }

        // Org-level aggregate (equipment_id = null)
        $this->upsertEquipmentKpi(
            $organizationId,
            0,
            (string) $year,
            str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            $orders
        );
    }

    private function upsertEquipmentKpi(
        int $organizationId,
        int $equipmentId,
        string $year,
        string $month,
        Collection $orders
    ): void {
        $breakdowns = $orders->where('order_type', MaintenanceOrder::TYPE_EMERGENCY);
        $planned    = $orders->whereIn('order_type', [
            MaintenanceOrder::TYPE_PREVENTIVE,
            MaintenanceOrder::TYPE_INSPECTION,
        ]);

        $totalDowntime = $orders->sum(fn ($o) =>
            $o->actual_start && $o->actual_end
                ? Carbon::parse($o->actual_start)->diffInMinutes(Carbon::parse($o->actual_end)) / 60
                : 0
        );

        $breakdownCount  = $breakdowns->count();
        $totalRepairTime = $breakdowns->sum(fn ($o) =>
            $o->actual_start && $o->actual_end
                ? Carbon::parse($o->actual_start)->diffInMinutes(Carbon::parse($o->actual_end)) / 60
                : 0
        );

        $daysInMonth   = Carbon::create((int) $year, (int) $month, 1)->daysInMonth;
        $calendarHours = $daysInMonth * 24;

        $mttr = $breakdownCount > 0 ? round($totalRepairTime / $breakdownCount, 2) : 0.0;
        // Approximate MTBF: (calendar hours - total repair time) / breakdown count
        $mtbf = $breakdownCount > 0
            ? round(($calendarHours - $totalRepairTime) / $breakdownCount, 2)
            : (float) $calendarHours;

        $availability = ($mtbf + $mttr) > 0
            ? round(($mtbf / ($mtbf + $mttr)) * 100, 2)
            : 100.0;

        $maintenanceCost = $orders->sum('actual_cost');
        $plannedHours    = $planned->sum(fn ($o) =>
            $o->actual_start && $o->actual_end
                ? Carbon::parse($o->actual_start)->diffInMinutes(Carbon::parse($o->actual_end)) / 60
                : 0
        );
        $unplannedHours = $breakdowns->sum(fn ($o) =>
            $o->actual_start && $o->actual_end
                ? Carbon::parse($o->actual_start)->diffInMinutes(Carbon::parse($o->actual_end)) / 60
                : 0
        );

        MaintenanceKpi::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'equipment_id'    => $equipmentId ?: null,
                'period_year'     => $year,
                'period_month'    => $month,
            ],
            [
                'mtbf_hours'                  => $mtbf,
                'mttr_hours'                  => $mttr,
                'availability_pct'            => $availability,
                'oee_pct'                     => $availability, // simplified: OEE = availability when performance/quality = 100%
                'breakdown_count'             => $breakdownCount,
                'total_downtime_hours'        => round($totalDowntime, 2),
                'planned_maintenance_hours'   => round($plannedHours, 2),
                'unplanned_maintenance_hours' => round($unplannedHours, 2),
                'maintenance_cost'            => $maintenanceCost,
            ]
        );
    }

    /**
     * Return historical KPIs for an equipment or org (latest N months).
     */
    public function getKpiDashboard(int $organizationId, ?int $equipmentId = null, int $months = 12): array
    {
        $query = MaintenanceKpi::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit($months);

        if ($equipmentId !== null) {
            $query->where('equipment_id', $equipmentId);
        } else {
            $query->whereNull('equipment_id');
        }

        $kpis   = $query->get();
        $latest = $kpis->first();

        return [
            'current_mtbf_hours'       => $latest?->mtbf_hours ?? 0,
            'current_mttr_hours'       => $latest?->mttr_hours ?? 0,
            'current_availability_pct' => $latest?->availability_pct ?? 0,
            'current_oee_pct'          => $latest?->oee_pct ?? 0,
            'trend'                    => $kpis->map(fn ($k) => [
                'period'           => "{$k->period_year}-{$k->period_month}",
                'mtbf_hours'       => $k->mtbf_hours,
                'mttr_hours'       => $k->mttr_hours,
                'availability_pct' => $k->availability_pct,
                'breakdown_count'  => $k->breakdown_count,
                'maintenance_cost' => $k->maintenance_cost,
            ])->values(),
        ];
    }

    /**
     * Cost analysis report: top cost drivers by equipment and order type.
     */
    public function getCostAnalysis(int $organizationId, string $fromDate, string $toDate): array
    {
        $byEquipment = MaintenanceOrder::query()
            ->selectRaw('equipment_id, order_type, SUM(actual_cost) as total_cost, COUNT(*) as order_count')
            ->where('organization_id', $organizationId)
            ->whereBetween('actual_end', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->where('status', MaintenanceOrder::STATUS_COMPLETED)
            ->groupBy('equipment_id', 'order_type')
            ->with('equipment:id,name,equipment_number')
            ->get();

        return [
            'by_equipment_and_type' => $byEquipment,
            'total_cost'            => $byEquipment->sum('total_cost'),
            'total_orders'          => $byEquipment->sum('order_count'),
        ];
    }
}
