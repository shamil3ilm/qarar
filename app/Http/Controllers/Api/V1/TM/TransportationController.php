<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TM;

use App\Http\Controllers\Controller;
use App\Models\TM\Carrier;
use App\Models\TM\FreightAgreement;
use App\Models\TM\FreightRateTable;
use App\Models\TM\FreightTenderRequest;
use App\Models\TM\LoadPlan;
use App\Models\TM\TransportationOrder;
use App\Services\TM\TransportationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportationController extends Controller
{
    public function __construct(
        private readonly TransportationService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Carriers
    // -------------------------------------------------------------------------

    public function listCarriers(Request $request): JsonResponse
    {
        $carriers = $this->service->listCarriers(
            $this->organizationId(),
            $request->only(['type', 'status', 'search'])
        );

        return $this->paginated($carriers);
    }

    public function createCarrier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'type' => 'required|in:road,air,sea,rail,courier,multimodal',
            'status' => 'sometimes|in:active,inactive,suspended',
            'scac_code' => 'nullable|string|max:10',
            'iata_code' => 'nullable|string|max:10',
            'country_code' => 'nullable|string|max:5',
            'currency_code' => 'sometimes|string|max:5',
            'payment_term_days' => 'sometimes|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $carrier = $this->service->createCarrier($this->organizationId(), $data);

        return $this->created($carrier);
    }

    public function showCarrier(Carrier $carrier): JsonResponse
    {
        $carrier->load(['services', 'performance' => fn ($q) => $q->orderByDesc('period_year')->orderByDesc('period_month')->limit(12)]);

        return $this->success($carrier);
    }

    public function updateCarrier(Request $request, Carrier $carrier): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'sometimes|in:road,air,sea,rail,courier,multimodal',
            'status' => 'sometimes|in:active,inactive,suspended',
            'scac_code' => 'nullable|string|max:10',
            'iata_code' => 'nullable|string|max:10',
            'country_code' => 'nullable|string|max:5',
            'currency_code' => 'sometimes|string|max:5',
            'payment_term_days' => 'sometimes|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $carrier = $this->service->updateCarrier($carrier, $data);

        return $this->success($carrier);
    }

    public function createCarrierService(Request $request, Carrier $carrier): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'mode' => 'required|in:road,air,sea,rail,courier',
            'transit_days_min' => 'required|integer|min:0',
            'transit_days_max' => 'required|integer|min:0',
            'is_tracking_available' => 'sometimes|boolean',
            'tracking_url_template' => 'nullable|string|max:500',
            'handles_dangerous_goods' => 'sometimes|boolean',
            'handles_refrigerated' => 'sometimes|boolean',
            'handles_oversized' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $service = $carrier->services()->create(
            array_merge($data, ['organization_id' => $this->organizationId()])
        );

        return $this->created($service);
    }

    public function recordCarrierPerformance(Request $request, Carrier $carrier): JsonResponse
    {
        $data = $request->validate([
            'period_year' => 'required|integer|min:2000|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
            'total_shipments' => 'required|integer|min:0',
            'on_time_deliveries' => 'required|integer|min:0',
            'late_deliveries' => 'required|integer|min:0',
            'damaged_shipments' => 'sometimes|integer|min:0',
            'lost_shipments' => 'sometimes|integer|min:0',
            'avg_transit_days' => 'nullable|numeric|min:0',
            'cost_variance_pct' => 'nullable|numeric',
        ]);

        $perf = $this->service->recordPerformance($carrier->id, $this->organizationId(), $data);

        return $this->success($perf);
    }

    // -------------------------------------------------------------------------
    // Freight Rate Tables
    // -------------------------------------------------------------------------

    public function listRateTables(Request $request): JsonResponse
    {
        $tables = $this->service->listRateTables(
            $this->organizationId(),
            $request->only(['carrier_id', 'is_active'])
        );

        return $this->paginated($tables);
    }

    public function createRateTable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'carrier_id' => 'nullable|integer',
            'carrier_service_id' => 'nullable|integer',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'currency_code' => 'sometimes|string|max:5',
            'basis' => 'required|in:weight,volume,piece,pallet,shipment',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $table = FreightRateTable::create(
            array_merge($data, ['organization_id' => $this->organizationId()])
        );

        return $this->created($table);
    }

    public function addRateLine(Request $request, FreightRateTable $rateTable): JsonResponse
    {
        $data = $request->validate([
            'origin_zone' => 'nullable|string|max:100',
            'destination_zone' => 'nullable|string|max:100',
            'weight_from' => 'sometimes|numeric|min:0',
            'weight_to' => 'nullable|numeric|min:0',
            'volume_from' => 'sometimes|numeric|min:0',
            'volume_to' => 'nullable|numeric|min:0',
            'base_rate' => 'required|numeric|min:0',
            'per_unit_rate' => 'sometimes|numeric|min:0',
            'min_charge' => 'sometimes|numeric|min:0',
            'max_charge' => 'nullable|numeric|min:0',
        ]);

        $line = $rateTable->rateLines()->create($data);

        return $this->created($line);
    }

    public function calculateCost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rate_table_id' => 'required|integer',
            'weight' => 'required|numeric|min:0',
            'volume' => 'required|numeric|min:0',
            'origin_zone' => 'nullable|string',
            'destination_zone' => 'nullable|string',
        ]);

        $result = $this->service->calculateFreightCost(
            $data['rate_table_id'],
            $data['weight'],
            $data['volume'],
            $data['origin_zone'] ?? null,
            $data['destination_zone'] ?? null
        );

        return $this->success($result);
    }

    // -------------------------------------------------------------------------
    // Freight Agreements
    // -------------------------------------------------------------------------

    public function listAgreements(Request $request): JsonResponse
    {
        $agreements = $this->service->listAgreements(
            $this->organizationId(),
            $request->only(['carrier_id', 'status'])
        );

        return $this->paginated($agreements);
    }

    public function createAgreement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => 'required|integer',
            'agreement_number' => 'required|string|max:50',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'currency_code' => 'sometimes|string|max:5',
            'rate_table_id' => 'nullable|integer',
            'annual_volume_commitment' => 'nullable|numeric|min:0',
            'annual_spend_commitment' => 'nullable|numeric|min:0',
            'payment_term_days' => 'sometimes|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $agreement = $this->service->createAgreement($this->organizationId(), $data);

        return $this->created($agreement->load('carrier'));
    }

    public function activateAgreement(FreightAgreement $agreement): JsonResponse
    {
        $agreement = $this->service->activateAgreement($agreement);

        return $this->success($agreement);
    }

    // -------------------------------------------------------------------------
    // Freight Tendering
    // -------------------------------------------------------------------------

    public function listTenderRequests(Request $request): JsonResponse
    {
        $tenders = $this->service->listTenderRequests(
            $this->organizationId(),
            $request->only(['status'])
        );

        return $this->paginated($tenders);
    }

    public function createTenderRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'origin_country' => 'nullable|string|max:5',
            'origin_zone' => 'nullable|string|max:100',
            'destination_country' => 'nullable|string|max:5',
            'destination_zone' => 'nullable|string|max:100',
            'transport_mode' => 'required|in:road,air,sea,rail,courier,multimodal',
            'total_weight' => 'sometimes|numeric|min:0',
            'total_volume' => 'sometimes|numeric|min:0',
            'shipment_count' => 'sometimes|integer|min:1',
            'has_dangerous_goods' => 'sometimes|boolean',
            'requires_refrigeration' => 'sometimes|boolean',
            'required_by_date' => 'nullable|date',
            'bid_deadline' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array',
            'items.*.description' => 'required|string|max:200',
            'items.*.weight' => 'required|numeric|min:0',
            'items.*.volume' => 'required|numeric|min:0',
            'items.*.quantity' => 'sometimes|numeric|min:0',
            'items.*.cargo_type' => 'nullable|string|max:50',
            'items.*.is_dangerous_goods' => 'sometimes|boolean',
            'items.*.un_number' => 'nullable|string|max:10',
        ]);

        $tender = $this->service->createTenderRequest($this->organizationId(), $data);

        return $this->created($tender);
    }

    public function openTenderRequest(FreightTenderRequest $tender): JsonResponse
    {
        $tender = $this->service->openTender($tender);

        return $this->success($tender);
    }

    public function submitBid(Request $request, FreightTenderRequest $tender): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => 'required|integer',
            'total_price' => 'required|numeric|min:0',
            'currency_code' => 'sometimes|string|max:5',
            'transit_days' => 'required|integer|min:0',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
            'breakdown' => 'nullable|array',
        ]);

        $bid = $this->service->submitBid($tender, $data['carrier_id'], $data);

        return $this->created($bid->load('carrier'));
    }

    public function evaluateBids(FreightTenderRequest $tender): JsonResponse
    {
        $tender = $this->service->evaluateBids($tender);

        return $this->success($tender);
    }

    public function awardTender(Request $request, FreightTenderRequest $tender): JsonResponse
    {
        $data = $request->validate([
            'bid_id' => 'required|integer',
        ]);

        $tender = $this->service->awardTender($tender, $data['bid_id']);

        return $this->success($tender);
    }

    // -------------------------------------------------------------------------
    // Transportation Orders
    // -------------------------------------------------------------------------

    public function listTransportationOrders(Request $request): JsonResponse
    {
        $orders = $this->service->listTransportationOrders(
            $this->organizationId(),
            $request->only(['status', 'type', 'carrier_id'])
        );

        return $this->paginated($orders);
    }

    public function createTransportationOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:outbound,inbound,internal',
            'carrier_id' => 'nullable|integer',
            'carrier_service_id' => 'nullable|integer',
            'origin_address' => 'nullable|string|max:500',
            'origin_country' => 'nullable|string|max:5',
            'destination_address' => 'nullable|string|max:500',
            'destination_country' => 'nullable|string|max:5',
            'planned_departure' => 'nullable|date',
            'planned_arrival' => 'nullable|date',
            'currency_code' => 'sometimes|string|max:5',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array',
            'items.*.description' => 'required|string|max:200',
            'items.*.quantity' => 'sometimes|numeric|min:0',
            'items.*.unit_of_measure' => 'sometimes|string|max:20',
            'items.*.weight' => 'required|numeric|min:0',
            'items.*.volume' => 'sometimes|numeric|min:0',
            'items.*.reference_type' => 'nullable|string|max:30',
            'items.*.reference_id' => 'nullable|integer',
            'items.*.reference_number' => 'nullable|string|max:50',
            'items.*.is_dangerous_goods' => 'sometimes|boolean',
            'items.*.un_number' => 'nullable|string|max:10',
        ]);

        $order = $this->service->createTransportationOrder($this->organizationId(), $data);

        return $this->created($order);
    }

    public function showTransportationOrder(TransportationOrder $order): JsonResponse
    {
        $order->load(['carrier', 'carrierService', 'loadPlan', 'items']);

        return $this->success($order);
    }

    public function updateOrderStatus(Request $request, TransportationOrder $order): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string',
            'tracking_number' => 'nullable|string|max:100',
            'freight_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $status = $data['status'];
        unset($data['status']);

        $order = $this->service->updateOrderStatus($order, $status, $data);

        return $this->success($order);
    }

    // -------------------------------------------------------------------------
    // Load Plans
    // -------------------------------------------------------------------------

    public function listLoadPlans(Request $request): JsonResponse
    {
        $plans = $this->service->listLoadPlans(
            $this->organizationId(),
            $request->only(['status'])
        );

        return $this->paginated($plans);
    }

    public function createLoadPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => 'nullable|integer',
            'carrier_service_id' => 'nullable|integer',
            'vehicle_type' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:100',
            'driver_contact' => 'nullable|string|max:50',
            'max_weight' => 'nullable|numeric|min:0',
            'max_volume' => 'nullable|numeric|min:0',
            'planned_departure' => 'nullable|date',
            'origin_location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        $plan = $this->service->createLoadPlan($this->organizationId(), $data);

        return $this->created($plan->load('carrier'));
    }

    public function showLoadPlan(LoadPlan $loadPlan): JsonResponse
    {
        $loadPlan->load(['carrier', 'items.transportationOrder']);

        return $this->success($loadPlan);
    }

    public function addToLoadPlan(Request $request, LoadPlan $loadPlan): JsonResponse
    {
        $data = $request->validate([
            'transportation_order_id' => 'required|integer',
        ]);

        $item = $this->service->addToLoadPlan($loadPlan, $data['transportation_order_id']);

        return $this->success([
            'item' => $item->load('transportationOrder'),
            'load_plan' => $loadPlan->fresh(),
        ]);
    }

    public function removeFromLoadPlan(Request $request, LoadPlan $loadPlan): JsonResponse
    {
        $data = $request->validate([
            'transportation_order_id' => 'required|integer',
        ]);

        $this->service->removeFromLoadPlan($loadPlan, $data['transportation_order_id']);

        return $this->success(['load_plan' => $loadPlan->fresh()]);
    }

    public function dispatchLoadPlan(Request $request, LoadPlan $loadPlan): JsonResponse
    {
        $data = $request->validate([
            'vehicle_plate' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:100',
            'driver_contact' => 'nullable|string|max:50',
        ]);

        $plan = $this->service->dispatchLoadPlan($loadPlan, $data);

        return $this->success($plan);
    }

    public function utilizationReport(Request $request): JsonResponse
    {
        $report = $this->service->getLoadPlanUtilizationReport($this->organizationId());

        return $this->success($report);
    }
}
