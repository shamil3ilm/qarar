<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\RealEstate;

use App\Http\Controllers\Controller;
use App\Models\RealEstate\Building;
use App\Models\RealEstate\ContractCondition;
use App\Models\RealEstate\ContractOption;
use App\Models\RealEstate\LeaseContract;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\RentalUnit;
use App\Models\RealEstate\SecurityDeposit;
use App\Models\RealEstate\ServiceChargeSettlement;
use App\Services\RealEstate\RealEstateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealEstateController extends Controller
{
    public function __construct(
        private readonly RealEstateService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Portfolios
    // -------------------------------------------------------------------------

    public function listPortfolios(): JsonResponse
    {
        $portfolios = $this->service->listPortfolios($this->organizationId());

        return $this->success($portfolios);
    }

    public function createPortfolio(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'type' => 'required|in:commercial,residential,industrial,mixed,retail,hospitality',
            'currency_code' => 'sometimes|string|max:5',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $portfolio = $this->service->createPortfolio($this->organizationId(), $data);

        return $this->created($portfolio);
    }

    public function portfolioOverview(): JsonResponse
    {
        $overview = $this->service->getPortfolioOverview($this->organizationId());

        return $this->success($overview);
    }

    // -------------------------------------------------------------------------
    // Properties
    // -------------------------------------------------------------------------

    public function listProperties(Request $request): JsonResponse
    {
        $properties = $this->service->listProperties(
            $this->organizationId(),
            $request->only(['portfolio_id', 'status'])
        );

        return $this->paginated($properties);
    }

    public function createProperty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'portfolio_id' => 'required|integer',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'type' => 'required|in:commercial,residential,industrial,mixed',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:5',
            'total_area_sqm' => 'sometimes|numeric|min:0',
            'land_area_sqm' => 'nullable|numeric|min:0',
            'current_valuation' => 'nullable|numeric|min:0',
            'valuation_currency' => 'sometimes|string|max:5',
            'valuation_date' => 'nullable|date',
            'ownership_type' => 'sometimes|in:owned,leased_in,managed',
            'status' => 'sometimes|in:active,inactive,under_development,disposed',
            'notes' => 'nullable|string',
        ]);

        $property = $this->service->createProperty($this->organizationId(), $data);

        return $this->created($property->load('portfolio'));
    }

    public function showProperty(Property $property): JsonResponse
    {
        $property->load(['portfolio', 'buildings.rentalUnits', 'serviceChargeSettlements']);

        return $this->success($property);
    }

    // -------------------------------------------------------------------------
    // Buildings & Floors
    // -------------------------------------------------------------------------

    public function createBuilding(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:200',
            'floors_above_ground' => 'sometimes|integer|min:0',
            'floors_below_ground' => 'sometimes|integer|min:0',
            'gross_area_sqm' => 'sometimes|numeric|min:0',
            'net_lettable_area_sqm' => 'sometimes|numeric|min:0',
            'year_built' => 'nullable|integer|min:1800|max:2100',
            'construction_type' => 'nullable|string|max:50',
            'status' => 'sometimes|in:active,under_renovation,demolished',
        ]);

        $building = $property->buildings()->create(
            array_merge($data, ['organization_id' => $this->organizationId()])
        );

        return $this->created($building);
    }

    public function createFloor(Request $request, Building $building): JsonResponse
    {
        $data = $request->validate([
            'floor_number' => 'required|integer',
            'floor_label' => 'nullable|string|max:50',
            'total_area_sqm' => 'sometimes|numeric|min:0',
            'lettable_area_sqm' => 'sometimes|numeric|min:0',
        ]);

        $floor = $building->floors()->create($data);

        return $this->created($floor);
    }

    // -------------------------------------------------------------------------
    // Rental Units
    // -------------------------------------------------------------------------

    public function listRentalUnits(Request $request): JsonResponse
    {
        $units = $this->service->listRentalUnits(
            $this->organizationId(),
            $request->only(['status', 'building_id', 'unit_type'])
        );

        return $this->paginated($units);
    }

    public function createRentalUnit(Request $request, Building $building): JsonResponse
    {
        $data = $request->validate([
            'floor_id' => 'nullable|integer',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:200',
            'unit_type' => 'required|in:office,retail,residential,parking,storage,warehouse,land',
            'area_sqm' => 'required|numeric|min:0',
            'usage_type' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'has_parking' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $unit = $building->rentalUnits()->create(
            array_merge($data, ['organization_id' => $this->organizationId(), 'status' => 'vacant'])
        );

        return $this->created($unit);
    }

    public function showRentalUnit(RentalUnit $unit): JsonResponse
    {
        $unit->load(['building.property', 'contracts' => fn ($q) => $q->orderByDesc('start_date')->limit(5)]);

        return $this->success($unit);
    }

    // -------------------------------------------------------------------------
    // Lease Contracts
    // -------------------------------------------------------------------------

    public function listContracts(Request $request): JsonResponse
    {
        $contracts = $this->service->listContracts(
            $this->organizationId(),
            $request->only(['status', 'contract_type'])
        );

        return $this->paginated($contracts);
    }

    public function createContract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_type' => 'required|in:lease_out,lease_in',
            'rental_unit_id' => 'required|integer',
            'counterparty_type' => 'nullable|string|max:30',
            'counterparty_id' => 'nullable|integer',
            'counterparty_name' => 'nullable|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notice_period_months' => 'sometimes|integer|min:0',
            'currency_code' => 'sometimes|string|max:5',
            'payment_day' => 'sometimes|integer|min:1|max:28',
            'payment_frequency' => 'sometimes|in:monthly,quarterly,semi_annual,annual',
            'auto_renew' => 'sometimes|boolean',
            'auto_renew_months' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'conditions' => 'sometimes|array',
            'conditions.*.condition_type' => 'required|string|max:30',
            'conditions.*.amount' => 'required|numeric|min:0',
            'conditions.*.basis' => 'sometimes|in:flat,per_sqm,pct_of_rent',
            'conditions.*.valid_from' => 'required|date',
            'conditions.*.escalation_type' => 'nullable|in:none,fixed_pct,cpi,stepped',
            'conditions.*.escalation_rate' => 'nullable|numeric|min:0',
            'conditions.*.escalation_frequency' => 'nullable|in:annual,biennial,quarterly',
            'conditions.*.is_taxable' => 'sometimes|boolean',
            'options' => 'sometimes|array',
            'options.*.option_type' => 'required|in:renewal,break,purchase,expansion,contraction',
            'options.*.exercise_deadline' => 'required|date',
            'options.*.new_term_months' => 'nullable|integer|min:1',
            'options.*.new_rent_amount' => 'nullable|numeric|min:0',
        ]);

        $contract = $this->service->createContract($this->organizationId(), $data);

        return $this->created($contract);
    }

    public function showContract(LeaseContract $contract): JsonResponse
    {
        $contract->load(['rentalUnit.building.property', 'conditions', 'options', 'securityDeposit']);

        return $this->success($contract);
    }

    public function activateContract(LeaseContract $contract): JsonResponse
    {
        $contract = $this->service->activateContract($contract);

        return $this->success($contract);
    }

    public function terminateContract(Request $request, LeaseContract $contract): JsonResponse
    {
        $data = $request->validate([
            'notice_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $contract = $this->service->terminateContract($contract, $data);

        return $this->success($contract);
    }

    public function expiringContracts(Request $request): JsonResponse
    {
        $days = (int) $request->input('within_days', 90);
        $contracts = $this->service->getExpiringContracts($this->organizationId(), $days);

        return $this->success($contracts);
    }

    // -------------------------------------------------------------------------
    // Contract Options
    // -------------------------------------------------------------------------

    public function exerciseOption(ContractOption $option): JsonResponse
    {
        $option = $this->service->exerciseOption($option);

        return $this->success($option);
    }

    // -------------------------------------------------------------------------
    // Rent Escalation
    // -------------------------------------------------------------------------

    public function dueEscalations(): JsonResponse
    {
        $conditions = $this->service->getDueEscalations($this->organizationId());

        return $this->success($conditions);
    }

    public function upcomingEscalations(Request $request): JsonResponse
    {
        $days = (int) $request->input('within_days', 30);
        $conditions = $this->service->getUpcomingEscalations($this->organizationId(), $days);

        return $this->success($conditions);
    }

    public function applyEscalation(Request $request, ContractCondition $condition): JsonResponse
    {
        $data = $request->validate([
            'new_amount' => 'required|numeric|min:0',
        ]);

        $newCondition = $this->service->applyEscalation($condition, $data['new_amount']);

        return $this->created($newCondition);
    }

    // -------------------------------------------------------------------------
    // Periodic Posting
    // -------------------------------------------------------------------------

    public function simulatePostingRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:rent,service_charge,deposit_interest,all',
            'period_year' => 'required|integer|min:2000|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $simulation = $this->service->simulatePostingRun(
            $this->organizationId(),
            $data['type'],
            $data['period_year'],
            $data['period_month']
        );

        return $this->success($simulation);
    }

    public function executePostingRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:rent,service_charge,deposit_interest,all',
            'period_year' => 'required|integer|min:2000|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $run = $this->service->executePostingRun(
            $this->organizationId(),
            $data['type'],
            $data['period_year'],
            $data['period_month']
        );

        return $this->created($run);
    }

    // -------------------------------------------------------------------------
    // Security Deposits
    // -------------------------------------------------------------------------

    public function createDeposit(Request $request, LeaseContract $contract): JsonResponse
    {
        $data = $request->validate([
            'required_amount' => 'required|numeric|min:0',
            'currency_code' => 'sometimes|string|max:5',
            'interest_rate_pct' => 'sometimes|numeric|min:0',
        ]);

        $deposit = $this->service->createSecurityDeposit($contract, $data);

        return $this->created($deposit);
    }

    public function recordDepositCollection(Request $request, SecurityDeposit $deposit): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        $deposit = $this->service->recordDepositCollection($deposit, $data['amount'], $data['date']);

        return $this->success($deposit);
    }

    public function accrueDepositInterest(SecurityDeposit $deposit): JsonResponse
    {
        $deposit = $this->service->accrueDepositInterest($deposit);

        return $this->success($deposit);
    }

    public function refundDeposit(Request $request, SecurityDeposit $deposit): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $deposit = $this->service->refundDeposit($deposit, $data['amount'], $data['reason']);

        return $this->success($deposit);
    }

    // -------------------------------------------------------------------------
    // Service Charge Settlement
    // -------------------------------------------------------------------------

    public function createServiceChargeSettlement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => 'required|integer',
            'settlement_year' => 'required|integer|min:2000|max:2100',
            'currency_code' => 'sometimes|string|max:5',
            'notes' => 'nullable|string',
            'cost_items' => 'required|array|min:1',
            'cost_items.*.cost_category' => 'required|string|max:100',
            'cost_items.*.actual_cost' => 'required|numeric|min:0',
            'cost_items.*.lettable_area_sqm' => 'sometimes|numeric|min:0',
            'cost_items.*.allocation_basis' => 'sometimes|in:area,equal,usage,custom',
            'cost_items.*.description' => 'nullable|string',
        ]);

        $settlement = $this->service->createServiceChargeSettlement($this->organizationId(), $data);

        return $this->created($settlement);
    }

    public function calculateSettlement(ServiceChargeSettlement $settlement): JsonResponse
    {
        $settlement = $this->service->calculateSettlement($settlement);

        return $this->success($settlement);
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    public function vacancyReport(Request $request): JsonResponse
    {
        $portfolioId = $request->integer('portfolio_id') ?: null;
        $report = $this->service->getVacancyReport($this->organizationId(), $portfolioId);

        return $this->success($report);
    }

    // -------------------------------------------------------------------------
    // IFRS 16 — Right-of-Use Asset & Lease Liability
    // -------------------------------------------------------------------------

    /**
     * Generate (or regenerate) the IFRS 16 amortisation schedule.
     *
     * POST /real-estate/contracts/{contract}/ifrs16/generate
     * Body: { "ibr_percent": 5.5, "commencement_date": "2026-01-01" (optional) }
     */
    public function generateIfrs16(Request $request, LeaseContract $contract): JsonResponse
    {
        $validated = $request->validate([
            'ibr_percent'       => 'required|numeric|min:0|max:100',
            'commencement_date' => 'nullable|date',
        ]);

        $result = $this->service->generateIfrs16Schedule(
            $contract,
            (float) $validated['ibr_percent'],
            $validated['commencement_date'] ?? null,
        );

        return $this->success([
            'rou_asset'       => $result['rou_asset'],
            'total_months'    => $result['total_months'],
            'monthly_payment' => $result['monthly_payment'],
            'ibr_percent'     => $result['ibr_percent'],
            'rows_generated'  => count($result['rows']),
        ], 'IFRS 16 schedule generated successfully.');
    }

    /**
     * Return the stored IFRS 16 amortisation schedule.
     *
     * GET /real-estate/contracts/{contract}/ifrs16/schedule
     */
    public function ifrs16Schedule(LeaseContract $contract): JsonResponse
    {
        $schedule = $this->service->getIfrs16Schedule($contract);

        return $this->success($schedule);
    }
}
