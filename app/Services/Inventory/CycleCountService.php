<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\CycleCountLine;
use App\Models\Inventory\CycleCountPlan;
use App\Models\Inventory\CycleCountSession;
use App\Models\Inventory\StockLevel;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CycleCountService
{
    public function createSession(CycleCountPlan $plan, int $counterId, Carbon $date): CycleCountSession
    {
        $session = CycleCountSession::create([
            'uuid'             => Str::uuid(),
            'organization_id'  => $plan->organization_id,
            'plan_id'          => $plan->id,
            'warehouse_id'     => $plan->warehouse_id,
            'session_date'     => $date->toDateString(),
            'counted_by'       => $counterId,
            'status'           => 'open',
        ]);

        // Seed lines from current stock levels
        $stockLevels = StockLevel::where('warehouse_id', $plan->warehouse_id)->get();
        foreach ($stockLevels as $stock) {
            CycleCountLine::create([
                'uuid'                   => Str::uuid(),
                'cycle_count_session_id' => $session->id,
                'product_id'             => $stock->product_id,
                'warehouse_location_id'  => $stock->location_id ?? null,
                'system_quantity'        => $stock->quantity_on_hand ?? 0,
                'status'                 => 'pending',
            ]);
        }

        return $session->load('lines');
    }

    public function recordCount(CycleCountLine $line, float $quantity): void
    {
        $variance = $quantity - (float) $line->system_quantity;
        $variancePct = $line->system_quantity > 0
            ? abs($variance / (float) $line->system_quantity * 100)
            : ($quantity > 0 ? 100 : 0);

        $line->update([
            'counted_quantity'    => $quantity,
            'variance_percentage' => $variancePct,
            'recount_required'    => $variancePct > 5,
            'status'              => 'counted',
        ]);
    }

    public function calculateVariances(CycleCountSession $session): array
    {
        return $session->lines()
            ->whereNotNull('counted_quantity')
            ->get()
            ->map(fn ($line) => [
                'product_id'      => $line->product_id,
                'system_qty'      => $line->system_quantity,
                'counted_qty'     => $line->counted_quantity,
                'variance'        => (float) $line->counted_quantity - (float) $line->system_quantity,
                'variance_pct'    => $line->variance_percentage,
                'recount_required' => $line->recount_required,
            ])
            ->toArray();
    }

    public function getAbcAnalysis(int $warehouseId): array
    {
        return StockLevel::where('warehouse_id', $warehouseId)
            ->with('product')
            ->orderByDesc('quantity_on_hand')
            ->limit(200)
            ->get()
            ->map(fn ($s, $i) => [
                'product_id'  => $s->product_id,
                'abc_class'   => $i < 20 ? 'A' : ($i < 60 ? 'B' : 'C'),
                'quantity'    => $s->quantity_on_hand,
            ])
            ->toArray();
    }
}
