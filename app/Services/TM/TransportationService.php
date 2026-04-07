<?php

declare(strict_types=1);

namespace App\Services\TM;

use App\Models\TM\Carrier;
use App\Models\TM\CarrierPerformance;
use App\Models\TM\FreightAgreement;
use App\Models\TM\FreightRateLine;
use App\Models\TM\FreightRateTable;
use App\Models\TM\FreightSurcharge;
use App\Models\TM\FreightTenderBid;
use App\Models\TM\FreightTenderRequest;
use App\Models\TM\LoadPlan;
use App\Models\TM\LoadPlanItem;
use App\Models\TM\TransportationOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TransportationService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    // -------------------------------------------------------------------------
    // Carrier Management
    // -------------------------------------------------------------------------

    public function listCarriers(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = Carrier::where('organization_id', $organizationId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('code', 'like', '%'.$filters['search'].'%');
            });
        }

        return $query->with('services')->orderBy('name')->paginate(20);
    }

    public function createCarrier(int $organizationId, array $data): Carrier
    {
        return Carrier::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function updateCarrier(Carrier $carrier, array $data): Carrier
    {
        $carrier->update($data);

        return $carrier->fresh();
    }

    public function getCarrierPerformance(int $carrierId, int $organizationId): Collection
    {
        return CarrierPerformance::where('organization_id', $organizationId)
            ->where('carrier_id', $carrierId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(24)
            ->get();
    }

    public function recordPerformance(int $carrierId, int $organizationId, array $data): CarrierPerformance
    {
        $perf = CarrierPerformance::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'carrier_id' => $carrierId,
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'],
            ],
            $data
        );

        // Recompute on_time_pct and rating
        $total = $perf->total_shipments;
        if ($total > 0) {
            $onTimePct = round(($perf->on_time_deliveries / $total) * 100, 2);
            // Simple rating: weighted average of on-time%, damage-free%, no-loss%
            $damageFree = round((($total - $perf->damaged_shipments) / $total) * 100, 2);
            $lossFreePct = round((($total - $perf->lost_shipments) / $total) * 100, 2);
            $rating = round(($onTimePct * 0.5 + $damageFree * 0.3 + $lossFreePct * 0.2) / 20, 2);
            // Convert 0–100 score to 0–5 rating
            $rating = min(5.0, $rating);

            $perf->update([
                'on_time_pct' => $onTimePct,
                'rating' => $rating,
            ]);
        }

        // Update carrier's aggregate rating (average of last 12 months)
        $avgRating = CarrierPerformance::where('carrier_id', $carrierId)
            ->where('organization_id', $organizationId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(12)
            ->avg('rating');

        if ($avgRating !== null) {
            Carrier::where('id', $carrierId)->update(['rating' => round($avgRating, 2)]);
        }

        return $perf->fresh();
    }

    // -------------------------------------------------------------------------
    // Freight Rate Engine
    // -------------------------------------------------------------------------

    public function listRateTables(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = FreightRateTable::where('organization_id', $organizationId);

        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->with('carrier')->orderByDesc('valid_from')->paginate(20);
    }

    public function calculateFreightCost(
        int $rateTableId,
        float $weight,
        float $volume,
        ?string $originZone = null,
        ?string $destinationZone = null
    ): array {
        $table = FreightRateTable::with(['rateLines', 'surcharges'])->findOrFail($rateTableId);

        // Find matching rate line
        $line = $table->rateLines
            ->filter(function (FreightRateLine $line) use ($weight, $volume, $originZone, $destinationZone) {
                if ($line->origin_zone !== null && $line->origin_zone !== $originZone) {
                    return false;
                }
                if ($line->destination_zone !== null && $line->destination_zone !== $destinationZone) {
                    return false;
                }
                if ($line->weight_to !== null && $weight > (float) $line->weight_to) {
                    return false;
                }
                if ($weight < (float) $line->weight_from) {
                    return false;
                }

                return true;
            })
            ->first();

        if (! $line) {
            throw new InvalidArgumentException('No matching rate line found for the given parameters.');
        }

        // Determine billing unit based on table basis
        $billingUnit = match ($table->basis) {
            'weight' => $weight,
            'volume' => $volume,
            'piece', 'pallet', 'shipment' => 1.0,
            default => $weight,
        };

        $baseAmount = bcadd(
            (string) $line->base_rate,
            bcmul((string) $line->per_unit_rate, (string) $billingUnit, 4),
            4
        );

        // Apply min/max charge
        if ((float) $line->min_charge > 0 && bccomp($baseAmount, (string) $line->min_charge, 4) < 0) {
            $baseAmount = (string) $line->min_charge;
        }
        if ($line->max_charge !== null && bccomp($baseAmount, (string) $line->max_charge, 4) > 0) {
            $baseAmount = (string) $line->max_charge;
        }

        // Apply surcharges
        $surchargeTotal = '0.0000';
        $surchargeBreakdown = [];

        foreach ($table->surcharges->where('is_active', true) as $surcharge) {
            $surchargeAmount = match ($surcharge->calculation_method) {
                'flat' => (string) $surcharge->value,
                'pct' => bcmul($baseAmount, bcdiv((string) $surcharge->value, '100', 6), 4),
                'per_kg' => bcmul((string) $surcharge->value, (string) $weight, 4),
                'per_cbm' => bcmul((string) $surcharge->value, (string) $volume, 4),
                'per_piece' => (string) $surcharge->value,
                default => '0.0000',
            };

            $surchargeTotal = bcadd($surchargeTotal, $surchargeAmount, 4);
            $surchargeBreakdown[] = [
                'code' => $surcharge->code,
                'name' => $surcharge->name,
                'type' => $surcharge->type,
                'amount' => $surchargeAmount,
            ];
        }

        $total = bcadd($baseAmount, $surchargeTotal, 4);

        return [
            'rate_table_id' => $table->id,
            'rate_table_code' => $table->code,
            'currency_code' => $table->currency_code,
            'base_amount' => $baseAmount,
            'surcharge_total' => $surchargeTotal,
            'total' => $total,
            'surcharges' => $surchargeBreakdown,
            'billing_weight' => $weight,
            'billing_volume' => $volume,
            'basis' => $table->basis,
        ];
    }

    // -------------------------------------------------------------------------
    // Freight Tendering
    // -------------------------------------------------------------------------

    public function listTenderRequests(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = FreightTenderRequest::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with(['awardedCarrier', 'bids'])->orderByDesc('created_at')->paginate(20);
    }

    public function createTenderRequest(int $organizationId, array $data): FreightTenderRequest
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $tenderNumber = $this->numberGenerator->generate($organizationId, 'freight_tender');

            $items = $data['items'] ?? [];
            unset($data['items']);

            $tender = FreightTenderRequest::create(array_merge($data, [
                'organization_id' => $organizationId,
                'tender_number' => $tenderNumber,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            foreach ($items as $item) {
                $tender->items()->create($item);
            }

            return $tender->load(['items']);
        });
    }

    public function openTender(FreightTenderRequest $tender): FreightTenderRequest
    {
        if ($tender->status !== 'draft') {
            throw new InvalidArgumentException('Only draft tender requests can be opened.');
        }

        $tender->update(['status' => 'open']);

        return $tender->fresh();
    }

    public function submitBid(FreightTenderRequest $tender, int $carrierId, array $data): FreightTenderBid
    {
        if (! $tender->isOpen()) {
            throw new InvalidArgumentException('Bids can only be submitted on open tender requests.');
        }

        if ($tender->bid_deadline && now()->gt($tender->bid_deadline)) {
            throw new InvalidArgumentException('Bid deadline has passed.');
        }

        return FreightTenderBid::updateOrCreate(
            ['tender_request_id' => $tender->id, 'carrier_id' => $carrierId],
            array_merge($data, [
                'carrier_id' => $carrierId,
                'status' => 'submitted',
                'submitted_at' => now(),
            ])
        );
    }

    public function evaluateBids(FreightTenderRequest $tender): FreightTenderRequest
    {
        if ($tender->status !== 'open') {
            throw new InvalidArgumentException('Only open tender requests can be evaluated.');
        }

        $tender->update(['status' => 'evaluating']);
        $tender->bids()->update(['status' => 'under_review']);

        return $tender->fresh(['bids.carrier']);
    }

    public function awardTender(FreightTenderRequest $tender, int $bidId): FreightTenderRequest
    {
        if ($tender->status !== 'evaluating') {
            throw new InvalidArgumentException('Tender must be in evaluating status to award.');
        }

        $bid = FreightTenderBid::where('tender_request_id', $tender->id)->findOrFail($bidId);

        return DB::transaction(function () use ($tender, $bid) {
            // Reject all other bids
            FreightTenderBid::where('tender_request_id', $tender->id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => 'rejected']);

            $bid->update(['status' => 'awarded']);

            $tender->update([
                'status' => 'awarded',
                'awarded_carrier_id' => $bid->carrier_id,
                'awarded_bid_id' => $bid->id,
                'awarded_at' => now(),
            ]);

            return $tender->fresh(['awardedCarrier', 'awardedBid']);
        });
    }

    // -------------------------------------------------------------------------
    // Transportation Orders
    // -------------------------------------------------------------------------

    public function listTransportationOrders(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = TransportationOrder::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }

        return $query->with(['carrier', 'carrierService'])->orderByDesc('created_at')->paginate(20);
    }

    public function createTransportationOrder(int $organizationId, array $data): TransportationOrder
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $orderNumber = $this->numberGenerator->generate($organizationId, 'transport_order');

            $items = $data['items'] ?? [];
            unset($data['items']);

            $order = TransportationOrder::create(array_merge($data, [
                'organization_id' => $organizationId,
                'order_number' => $orderNumber,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            // Aggregate weight/volume from items
            $totalWeight = $order->items->sum('weight');
            $totalVolume = $order->items->sum('volume');
            $hasDg = $order->items->contains('is_dangerous_goods', true);

            $order->update([
                'total_weight' => $totalWeight,
                'total_volume' => $totalVolume,
                'has_dangerous_goods' => $hasDg,
            ]);

            return $order->load(['items', 'carrier', 'carrierService']);
        });
    }

    public function updateOrderStatus(TransportationOrder $order, string $newStatus, array $data = []): TransportationOrder
    {
        $allowed = [
            'draft' => ['planned', 'cancelled'],
            'planned' => ['tendered', 'carrier_assigned', 'cancelled'],
            'tendered' => ['carrier_assigned', 'cancelled'],
            'carrier_assigned' => ['in_transit', 'cancelled'],
            'in_transit' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        if (! in_array($newStatus, $allowed[$order->status] ?? [], true)) {
            throw new InvalidArgumentException(
                "Cannot transition transportation order from '{$order->status}' to '{$newStatus}'."
            );
        }

        $updateData = array_merge(['status' => $newStatus], $data);

        if ($newStatus === 'in_transit' && empty($updateData['actual_departure'])) {
            $updateData['actual_departure'] = now();
        }
        if ($newStatus === 'delivered' && empty($updateData['actual_arrival'])) {
            $updateData['actual_arrival'] = now();
        }

        $order->update($updateData);

        return $order->fresh(['carrier', 'carrierService']);
    }

    // -------------------------------------------------------------------------
    // Load Building
    // -------------------------------------------------------------------------

    public function listLoadPlans(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = LoadPlan::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with(['carrier', 'items'])->orderByDesc('planned_departure')->paginate(20);
    }

    public function createLoadPlan(int $organizationId, array $data): LoadPlan
    {
        $planNumber = $this->numberGenerator->generate($organizationId, 'load_plan');

        return LoadPlan::create(array_merge($data, [
            'organization_id' => $organizationId,
            'plan_number' => $planNumber,
            'status' => 'open',
            'current_weight' => 0,
            'current_volume' => 0,
            'created_by' => Auth::id(),
        ]));
    }

    public function addToLoadPlan(LoadPlan $loadPlan, int $transportationOrderId): LoadPlanItem
    {
        if (! in_array($loadPlan->status, ['open', 'building'], true)) {
            throw new InvalidArgumentException('Load plan is not open for adding orders.');
        }

        $order = TransportationOrder::where('organization_id', $loadPlan->organization_id)
            ->findOrFail($transportationOrderId);

        if (! $loadPlan->isCapacityAvailable((float) $order->total_weight, (float) $order->total_volume)) {
            throw new RuntimeException('Insufficient capacity in load plan for this transportation order.');
        }

        return DB::transaction(function () use ($loadPlan, $order) {
            $item = LoadPlanItem::create([
                'load_plan_id' => $loadPlan->id,
                'transportation_order_id' => $order->id,
                'loading_sequence' => $loadPlan->items()->max('loading_sequence') + 1,
            ]);

            // Update load plan totals
            $newWeight = bcadd((string) $loadPlan->current_weight, (string) $order->total_weight, 3);
            $newVolume = bcadd((string) $loadPlan->current_volume, (string) $order->total_volume, 4);

            $weightPct = $loadPlan->max_weight
                ? round((float) $newWeight / (float) $loadPlan->max_weight * 100, 2)
                : 0;
            $volumePct = $loadPlan->max_volume
                ? round((float) $newVolume / (float) $loadPlan->max_volume * 100, 2)
                : 0;

            $loadPlan->update([
                'status' => 'building',
                'current_weight' => $newWeight,
                'current_volume' => $newVolume,
                'utilization_weight_pct' => $weightPct,
                'utilization_volume_pct' => $volumePct,
            ]);

            // Link transportation order to this load plan
            $order->update(['load_plan_id' => $loadPlan->id]);

            return $item;
        });
    }

    public function removeFromLoadPlan(LoadPlan $loadPlan, int $transportationOrderId): void
    {
        if (! in_array($loadPlan->status, ['open', 'building'], true)) {
            throw new InvalidArgumentException('Cannot remove items from a finalized or dispatched load plan.');
        }

        $item = LoadPlanItem::where('load_plan_id', $loadPlan->id)
            ->where('transportation_order_id', $transportationOrderId)
            ->firstOrFail();

        $order = TransportationOrder::findOrFail($transportationOrderId);

        DB::transaction(function () use ($loadPlan, $item, $order) {
            $item->delete();

            $newWeight = bcsub((string) $loadPlan->current_weight, (string) $order->total_weight, 3);
            $newVolume = bcsub((string) $loadPlan->current_volume, (string) $order->total_volume, 4);
            $newWeight = max('0.000', $newWeight);
            $newVolume = max('0.0000', $newVolume);

            $weightPct = $loadPlan->max_weight
                ? round((float) $newWeight / (float) $loadPlan->max_weight * 100, 2)
                : 0;
            $volumePct = $loadPlan->max_volume
                ? round((float) $newVolume / (float) $loadPlan->max_volume * 100, 2)
                : 0;

            $remaining = $loadPlan->items()->count();
            $newStatus = $remaining === 0 ? 'open' : 'building';

            $loadPlan->update([
                'status' => $newStatus,
                'current_weight' => $newWeight,
                'current_volume' => $newVolume,
                'utilization_weight_pct' => $weightPct,
                'utilization_volume_pct' => $volumePct,
            ]);

            $order->update(['load_plan_id' => null]);
        });
    }

    public function dispatchLoadPlan(LoadPlan $loadPlan, array $data = []): LoadPlan
    {
        if (! in_array($loadPlan->status, ['building', 'finalized'], true)) {
            throw new InvalidArgumentException('Load plan must be building or finalized to dispatch.');
        }

        if ($loadPlan->items()->count() === 0) {
            throw new RuntimeException('Cannot dispatch an empty load plan.');
        }

        return DB::transaction(function () use ($loadPlan, $data) {
            $loadPlan->update(array_merge([
                'status' => 'dispatched',
                'actual_departure' => now(),
            ], $data));

            // Transition all linked transportation orders to in_transit
            TransportationOrder::where('load_plan_id', $loadPlan->id)
                ->whereIn('status', ['planned', 'carrier_assigned'])
                ->update([
                    'status' => 'in_transit',
                    'actual_departure' => now(),
                ]);

            return $loadPlan->fresh(['carrier', 'items.transportationOrder']);
        });
    }

    public function getLoadPlanUtilizationReport(int $organizationId): array
    {
        $plans = LoadPlan::where('organization_id', $organizationId)
            ->whereIn('status', ['building', 'finalized', 'dispatched'])
            ->with('carrier')
            ->orderByDesc('planned_departure')
            ->limit(30)
            ->get();

        return $plans->map(function (LoadPlan $plan) {
            return [
                'plan_number' => $plan->plan_number,
                'status' => $plan->status,
                'carrier' => $plan->carrier?->name,
                'vehicle_type' => $plan->vehicle_type,
                'weight_utilization_pct' => $plan->utilization_weight_pct,
                'volume_utilization_pct' => $plan->utilization_volume_pct,
                'current_weight' => $plan->current_weight,
                'max_weight' => $plan->max_weight,
                'current_volume' => $plan->current_volume,
                'max_volume' => $plan->max_volume,
                'planned_departure' => $plan->planned_departure,
                'order_count' => $plan->items()->count(),
            ];
        })->all();
    }

    // -------------------------------------------------------------------------
    // Freight Agreements
    // -------------------------------------------------------------------------

    public function listAgreements(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = FreightAgreement::where('organization_id', $organizationId);

        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with(['carrier', 'rateTable'])->orderByDesc('valid_from')->paginate(20);
    }

    public function createAgreement(int $organizationId, array $data): FreightAgreement
    {
        return FreightAgreement::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function activateAgreement(FreightAgreement $agreement): FreightAgreement
    {
        if ($agreement->status !== 'draft') {
            throw new InvalidArgumentException('Only draft agreements can be activated.');
        }

        $agreement->update(['status' => 'active']);

        return $agreement->fresh();
    }
}
