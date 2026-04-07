<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\ContractCondition;
use App\Models\RealEstate\ContractOption;
use App\Models\RealEstate\Ifrs16Schedule;
use App\Models\RealEstate\LeaseContract;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\PostingRun;
use App\Models\RealEstate\PostingRunItem;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\RentalUnit;
use App\Models\RealEstate\SecurityDeposit;
use App\Models\Accounting\Account;
use App\Models\RealEstate\ServiceChargeAllocation;
use App\Models\RealEstate\ServiceChargeItem;
use App\Models\RealEstate\ServiceChargeSettlement;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RealEstateService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
        private readonly JournalService $journalService,
    ) {}

    // -------------------------------------------------------------------------
    // Portfolio & Object Hierarchy
    // -------------------------------------------------------------------------

    public function listPortfolios(int $organizationId): Collection
    {
        return Portfolio::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('properties')
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    public function createPortfolio(int $organizationId, array $data): Portfolio
    {
        return Portfolio::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function getPortfolioOverview(int $organizationId): array
    {
        $portfolios = Portfolio::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with(['properties.buildings.rentalUnits'])
            ->limit(50)
            ->get();

        return $portfolios->map(function (Portfolio $portfolio) {
            $units = $portfolio->properties
                ->flatMap(fn ($p) => $p->buildings)
                ->flatMap(fn ($b) => $b->rentalUnits);

            $totalUnits = $units->count();
            $vacantUnits = $units->where('status', 'vacant')->count();
            $occupiedUnits = $units->where('status', 'occupied')->count();
            $totalArea = $units->sum('area_sqm');
            $vacantArea = $units->where('status', 'vacant')->sum('area_sqm');

            return [
                'portfolio_id' => $portfolio->id,
                'portfolio_code' => $portfolio->code,
                'portfolio_name' => $portfolio->name,
                'type' => $portfolio->type,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate_pct' => $totalUnits > 0
                    ? round($occupiedUnits / $totalUnits * 100, 2)
                    : 0,
                'total_area_sqm' => $totalArea,
                'vacant_area_sqm' => $vacantArea,
                'vacancy_rate_pct' => $totalArea > 0
                    ? round((float) $vacantArea / (float) $totalArea * 100, 2)
                    : 0,
            ];
        })->all();
    }

    public function listProperties(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = Property::where('organization_id', $organizationId);

        if (! empty($filters['portfolio_id'])) {
            $query->where('portfolio_id', $filters['portfolio_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('portfolio')->orderBy('name')->paginate(20);
    }

    public function createProperty(int $organizationId, array $data): Property
    {
        return Property::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function listRentalUnits(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = RentalUnit::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['building_id'])) {
            $query->where('building_id', $filters['building_id']);
        }
        if (! empty($filters['unit_type'])) {
            $query->where('unit_type', $filters['unit_type']);
        }

        return $query->with(['building.property', 'activeContract'])->orderBy('code')->paginate(20);
    }

    // -------------------------------------------------------------------------
    // Lease Contract Management
    // -------------------------------------------------------------------------

    public function listContracts(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = LeaseContract::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }

        return $query->with(['rentalUnit.building', 'conditions', 'securityDeposit'])
            ->orderByDesc('start_date')
            ->paginate(20);
    }

    public function createContract(int $organizationId, array $data): LeaseContract
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $unit = RentalUnit::where('organization_id', $organizationId)
                ->findOrFail($data['rental_unit_id']);

            if (! $unit->isVacant() && ($data['contract_type'] ?? 'lease_out') === 'lease_out') {
                throw new InvalidArgumentException("Unit '{$unit->code}' is not vacant.");
            }

            $contractNumber = $this->numberGenerator->generate('LEASE', null, $organizationId);

            $conditions = $data['conditions'] ?? [];
            $options = $data['options'] ?? [];
            unset($data['conditions'], $data['options']);

            $contract = LeaseContract::create(array_merge($data, [
                'organization_id' => $organizationId,
                'contract_number' => $contractNumber,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]));

            foreach ($conditions as $condition) {
                $contract->conditions()->create($condition);
            }

            foreach ($options as $option) {
                $contract->options()->create($option);
            }

            return $contract->load(['conditions', 'options', 'rentalUnit']);
        });
    }

    public function activateContract(LeaseContract $contract): LeaseContract
    {
        if ($contract->status !== 'draft') {
            throw new InvalidArgumentException('Only draft contracts can be activated.');
        }

        return DB::transaction(function () use ($contract) {
            $contract->update(['status' => 'active']);

            // Mark unit as occupied
            if ($contract->isLeaseOut()) {
                $contract->rentalUnit->update(['status' => 'occupied']);
            }

            return $contract->fresh(['rentalUnit', 'conditions']);
        });
    }

    public function terminateContract(LeaseContract $contract, array $data): LeaseContract
    {
        if (! in_array($contract->status, ['active', 'notice_given'], true)) {
            throw new InvalidArgumentException('Contract must be active or in notice period to terminate.');
        }

        return DB::transaction(function () use ($contract, $data) {
            $contract->update(array_merge(['status' => 'terminated'], $data));

            if ($contract->isLeaseOut()) {
                $contract->rentalUnit->update(['status' => 'vacant']);
            }

            return $contract->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Rent Escalation
    // -------------------------------------------------------------------------

    public function applyEscalation(ContractCondition $condition, float $newRate): ContractCondition
    {
        return DB::transaction(function () use ($condition, $newRate) {
            // Expire current condition
            $condition->update([
                'valid_to' => now()->toDateString(),
                'is_active' => false,
            ]);

            // Create new version
            return ContractCondition::create([
                'contract_id' => $condition->contract_id,
                'condition_type' => $condition->condition_type,
                'description' => $condition->description,
                'amount' => $newRate,
                'basis' => $condition->basis,
                'valid_from' => now()->addDay()->toDateString(),
                'escalation_type' => $condition->escalation_type,
                'escalation_rate' => $condition->escalation_rate,
                'escalation_index' => $condition->escalation_index,
                'escalation_frequency' => $condition->escalation_frequency,
                'next_escalation_date' => $this->computeNextEscalationDate(
                    now()->addDay(),
                    $condition->escalation_frequency
                ),
                'is_taxable' => $condition->is_taxable,
                'is_active' => true,
            ]);
        });
    }

    /** Returns conditions that are due for escalation today or overdue. */
    public function getDueEscalations(int $organizationId): Collection
    {
        return ContractCondition::whereHas('contract', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'active'))
            ->where('is_active', true)
            ->whereNotNull('next_escalation_date')
            ->whereNotIn('escalation_type', ['none', ''])
            ->where('next_escalation_date', '<=', now()->toDateString())
            ->with('contract.rentalUnit')
            ->limit(200)
            ->get();
    }

    private function computeNextEscalationDate(Carbon $from, ?string $frequency): ?string
    {
        return match ($frequency) {
            'annual' => $from->addYear()->toDateString(),
            'biennial' => $from->addYears(2)->toDateString(),
            'quarterly' => $from->addMonths(3)->toDateString(),
            default => null,
        };
    }

    // -------------------------------------------------------------------------
    // Periodic Posting (Rent Invoicing)
    // -------------------------------------------------------------------------

    public function simulatePostingRun(int $organizationId, string $type, int $year, int $month): array
    {
        $items = [];
        $totalAmount = '0.0000';

        $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        LeaseContract::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->with(['activeConditions', 'rentalUnit'])
            ->chunkById(100, function ($contracts) use ($type, &$items, &$totalAmount) {
                foreach ($contracts as $contract) {
                    foreach ($contract->activeConditions as $condition) {
                        if ($type !== 'all' && $condition->condition_type !== $type) {
                            continue;
                        }

                        $amount      = $condition->computeAmount((float) $contract->rentalUnit?->area_sqm ?? 0);
                        $taxAmount   = $condition->is_taxable ? bcmul($amount, '0.15', 4) : '0.0000';
                        $totalLine   = bcadd($amount, $taxAmount, 4);
                        $totalAmount = bcadd($totalAmount, $totalLine, 4);

                        $items[] = [
                            'contract_number' => $contract->contract_number,
                            'unit_code'       => $contract->rentalUnit?->code,
                            'condition_type'  => $condition->condition_type,
                            'amount'          => $amount,
                            'tax_amount'      => $taxAmount,
                            'total_amount'    => $totalLine,
                        ];
                    }
                }
            });

        return [
            'organization_id' => $organizationId,
            'type' => $type,
            'period_year' => $year,
            'period_month' => $month,
            'posting_date' => $postingDate,
            'contracts_to_process' => count(array_unique(array_column($items, 'contract_number'))),
            'total_amount' => $totalAmount,
            'items' => $items,
        ];
    }

    public function executePostingRun(int $organizationId, string $type, int $year, int $month): PostingRun
    {
        // Check for duplicate posting
        $existing = PostingRun::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'posted')
            ->first();

        if ($existing) {
            throw new RuntimeException("Posting run for {$type} {$year}/{$month} already exists (#{$existing->run_number}).");
        }

        return DB::transaction(function () use ($organizationId, $type, $year, $month) {
            $runNumber = $this->numberGenerator->generate('RE-RUN', null, $organizationId);
            $postingDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $run = PostingRun::create([
                'organization_id' => $organizationId,
                'run_number' => $runNumber,
                'type' => $type,
                'posting_date' => $postingDate,
                'period_year' => $year,
                'period_month' => $month,
                'status' => 'draft',
                'currency_code' => 'SAR',
            ]);

            $totalAmount        = '0.0000';
            $contractsProcessed = 0;

            LeaseContract::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->with(['activeConditions', 'rentalUnit'])
                ->chunkById(100, function ($contracts) use ($run, $type, &$totalAmount, &$contractsProcessed) {
                    foreach ($contracts as $contract) {
                        $contractHasItems = false;

                        foreach ($contract->activeConditions as $condition) {
                            if ($type !== 'all' && $condition->condition_type !== $type) {
                                continue;
                            }

                            $amount    = $condition->computeAmount((float) $contract->rentalUnit?->area_sqm ?? 0);
                            $taxAmount = $condition->is_taxable ? bcmul($amount, '0.15', 4) : '0.0000';
                            $totalLine = bcadd($amount, $taxAmount, 4);

                            PostingRunItem::create([
                                'posting_run_id' => $run->id,
                                'contract_id'    => $contract->id,
                                'condition_id'   => $condition->id,
                                'condition_type' => $condition->condition_type,
                                'amount'         => $amount,
                                'tax_amount'     => $taxAmount,
                                'total_amount'   => $totalLine,
                                'status'         => 'posted',
                            ]);

                            $totalAmount      = bcadd($totalAmount, $totalLine, 4);
                            $contractHasItems = true;
                        }

                        if ($contractHasItems) {
                            $contractsProcessed++;
                        }
                    }
                });

            $run->update([
                'status' => 'posted',
                'contracts_processed' => $contractsProcessed,
                'total_amount' => $totalAmount,
                'executed_by' => Auth::id(),
                'executed_at' => now(),
            ]);

            // Post to General Ledger: Debit AR, Credit Rental Income
            $this->postRunToGeneralLedger($run, $organizationId, $postingDate, $totalAmount);

            return $run->load('items');
        });
    }

    /**
     * Create a balanced GL journal entry for the posting run.
     * Silently skips if the required accounts are not configured.
     */
    private function postRunToGeneralLedger(PostingRun $run, int $organizationId, string $postingDate, string $totalAmount): void
    {
        if (bccomp($totalAmount, '0', 4) <= 0) {
            return;
        }

        $arAccount = Account::where('organization_id', $organizationId)
            ->whereIn('type', ['receivable', 'asset'])
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'receivable' THEN 0 ELSE 1 END")
            ->first();

        $incomeAccount = Account::where('organization_id', $organizationId)
            ->whereIn('type', ['income', 'revenue'])
            ->where('is_active', true)
            ->first();

        if ($arAccount === null || $incomeAccount === null) {
            \Illuminate\Support\Facades\Log::info('RE-FX posting run: GL accounts not found, skipping journal entry', [
                'run_number' => $run->run_number,
                'ar_found'   => $arAccount !== null,
                'inc_found'  => $incomeAccount !== null,
            ]);

            return;
        }

        try {
            $this->journalService->createEntry(
                entryData: [
                    'organization_id'  => $organizationId,
                    'entry_date'       => $postingDate,
                    'reference_type'   => 're_posting_run',
                    'reference_id'     => $run->id,
                    'reference_number' => $run->run_number,
                    'description'      => "RE-FX periodic posting run {$run->run_number}",
                    'currency_code'    => $run->currency_code ?? 'SAR',
                    'status'           => 'posted',
                    'created_by'       => Auth::id(),
                ],
                lines: [
                    [
                        'account_id'  => $arAccount->id,
                        'debit'       => $totalAmount,
                        'credit'      => 0,
                        'description' => "Rent receivable — {$run->run_number}",
                        'line_order'  => 0,
                    ],
                    [
                        'account_id'  => $incomeAccount->id,
                        'debit'       => 0,
                        'credit'      => $totalAmount,
                        'description' => "Rental income — {$run->run_number}",
                        'line_order'  => 1,
                    ],
                ],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('RE-FX posting run: GL journal entry failed', [
                'run_number' => $run->run_number,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Security Deposit Management
    // -------------------------------------------------------------------------

    public function createSecurityDeposit(LeaseContract $contract, array $data): SecurityDeposit
    {
        if ($contract->securityDeposit()->exists()) {
            throw new InvalidArgumentException('A security deposit already exists for this contract.');
        }

        $depositNumber = $this->numberGenerator->generate('RE-DEP', null, $contract->organization_id);

        return SecurityDeposit::create(array_merge($data, [
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'deposit_number' => $depositNumber,
            'status' => 'pending',
        ]));
    }

    public function recordDepositCollection(SecurityDeposit $deposit, float $amount, string $date): SecurityDeposit
    {
        $newCollected = bcadd((string) $deposit->collected_amount, (string) $amount, 4);

        $status = bccomp($newCollected, (string) $deposit->required_amount, 4) >= 0
            ? 'collected'
            : 'partial';

        $deposit->update([
            'collected_amount' => $newCollected,
            'collected_date' => $deposit->collected_date ?? $date,
            'status' => $status,
        ]);

        return $deposit->fresh();
    }

    public function accrueDepositInterest(SecurityDeposit $deposit): SecurityDeposit
    {
        $interest = $deposit->computeCurrentInterest();
        $deposit->update(['accrued_interest' => $interest]);

        return $deposit->fresh();
    }

    public function refundDeposit(SecurityDeposit $deposit, float $amount, string $reason): SecurityDeposit
    {
        if ((float) $deposit->collected_amount < $amount) {
            throw new InvalidArgumentException('Refund amount exceeds collected deposit.');
        }

        $newRefunded = bcadd((string) $deposit->refunded_amount, (string) $amount, 4);
        $status = bccomp($newRefunded, (string) $deposit->collected_amount, 4) >= 0
            ? 'refunded'
            : 'partially_refunded';

        $deposit->update([
            'refunded_amount' => $newRefunded,
            'refund_date' => now()->toDateString(),
            'refund_reason' => $reason,
            'status' => $status,
        ]);

        return $deposit->fresh();
    }

    // -------------------------------------------------------------------------
    // Service Charge Settlement
    // -------------------------------------------------------------------------

    public function createServiceChargeSettlement(int $organizationId, array $data): ServiceChargeSettlement
    {
        $settlementNumber = $this->numberGenerator->generate('RE-SET', null, $organizationId);

        $costItems = $data['cost_items'] ?? [];
        unset($data['cost_items']);

        return DB::transaction(function () use ($organizationId, $data, $costItems, $settlementNumber) {
            $settlement = ServiceChargeSettlement::create(array_merge($data, [
                'organization_id' => $organizationId,
                'settlement_number' => $settlementNumber,
                'status' => 'draft',
            ]));

            foreach ($costItems as $item) {
                $settlement->costItems()->create($item);
            }

            return $settlement->load('costItems');
        });
    }

    public function calculateSettlement(ServiceChargeSettlement $settlement): ServiceChargeSettlement
    {
        if ($settlement->status !== 'draft') {
            throw new InvalidArgumentException('Only draft settlements can be calculated.');
        }

        return DB::transaction(function () use ($settlement) {
            $property = $settlement->property;
            $costItems = $settlement->costItems;

            // Compute cost per sqm for each item
            foreach ($costItems as $item) {
                if ((float) $item->lettable_area_sqm > 0) {
                    $costPerSqm = bcdiv((string) $item->actual_cost, (string) $item->lettable_area_sqm, 6);
                    $item->update(['cost_per_sqm' => $costPerSqm]);
                }
            }

            $totalActualCosts = $costItems->sum('actual_cost');

            // Get active contracts for this property for the settlement year
            $contracts = LeaseContract::where('organization_id', $settlement->organization_id)
                ->where('status', 'active')
                ->whereHas('rentalUnit.building', fn ($q) => $q->where('property_id', $property->id))
                ->with(['rentalUnit', 'conditions' => fn ($q) => $q->where('condition_type', 'service_charge')->where('is_active', true)])
                ->get();

            $totalArea = $contracts->sum(fn ($c) => (float) $c->rentalUnit?->area_sqm ?? 0);
            $totalBilled = '0.0000';

            // Delete existing allocations before recalculating
            $settlement->allocations()->delete();

            foreach ($contracts as $contract) {
                $unitArea = (float) $contract->rentalUnit?->area_sqm ?? 0;
                $allocationPct = $totalArea > 0 ? round($unitArea / $totalArea * 100, 4) : 0;
                $actualAmount = bcmul((string) $totalActualCosts, bcdiv((string) $allocationPct, '100', 6), 4);

                // What was billed on account (service_charge conditions * 12 months)
                $monthlyBilled = $contract->conditions->sum('amount');
                $annualBilled = bcmul((string) $monthlyBilled, '12', 4);

                $adjustment = bcsub((string) $actualAmount, $annualBilled, 4);
                $totalBilled = bcadd($totalBilled, $annualBilled, 4);

                ServiceChargeAllocation::create([
                    'settlement_id' => $settlement->id,
                    'contract_id' => $contract->id,
                    'unit_area_sqm' => $unitArea,
                    'allocation_pct' => $allocationPct,
                    'actual_amount' => $actualAmount,
                    'billed_amount' => $annualBilled,
                    'adjustment_amount' => $adjustment,
                ]);
            }

            $totalAdjustment = bcsub((string) $totalActualCosts, $totalBilled, 4);

            $settlement->update([
                'status' => 'calculated',
                'total_actual_costs' => $totalActualCosts,
                'total_billed_to_tenants' => $totalBilled,
                'total_adjustment' => $totalAdjustment,
            ]);

            return $settlement->fresh(['costItems', 'allocations.contract']);
        });
    }

    // -------------------------------------------------------------------------
    // Contract Options
    // -------------------------------------------------------------------------

    public function exerciseOption(ContractOption $option): ContractOption
    {
        if (! $option->isExercisable()) {
            throw new InvalidArgumentException('This option cannot be exercised (expired, already exercised, or outside exercise window).');
        }

        return DB::transaction(function () use ($option) {
            $option->update([
                'status' => 'exercised',
                'exercised_at' => now()->toDateString(),
            ]);

            $contract = $option->contract;

            if ($option->option_type === 'renewal') {
                $newEndDate = $contract->end_date
                    ? Carbon::parse($contract->end_date)->addMonths($option->new_term_months ?? 12)->toDateString()
                    : null;

                $contract->update(['end_date' => $newEndDate]);

                // If a new rent amount was agreed, update base_rent condition
                if ($option->new_rent_amount !== null) {
                    $baseRentCondition = $contract->conditions()
                        ->where('condition_type', 'base_rent')
                        ->where('is_active', true)
                        ->first();

                    if ($baseRentCondition) {
                        $this->applyEscalation($baseRentCondition, (float) $option->new_rent_amount);
                    }
                }
            }

            if ($option->option_type === 'break') {
                $contract->update(['status' => 'notice_given', 'notice_date' => now()->toDateString()]);
            }

            return $option->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Vacancy & Reporting
    // -------------------------------------------------------------------------

    public function getVacancyReport(int $organizationId, ?int $portfolioId = null): array
    {
        $baseQuery = RentalUnit::where('organization_id', $organizationId);

        if ($portfolioId) {
            $baseQuery->whereHas('building.property', fn ($q) => $q->where('portfolio_id', $portfolioId));
        }

        // DB-level totals — avoids loading all unit rows into memory.
        $totals = (clone $baseQuery)
            ->selectRaw(
                'COUNT(*) as total,
                 COALESCE(SUM(area_sqm), 0) as total_area,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as vacant_count,
                 COALESCE(SUM(CASE WHEN status = ? THEN area_sqm ELSE 0 END), 0) as vacant_area',
                ['vacant', 'vacant']
            )
            ->first();

        // DB-level per-type breakdown.
        $byType = (clone $baseQuery)
            ->selectRaw(
                'unit_type,
                 COUNT(*) as total,
                 COALESCE(SUM(area_sqm), 0) as total_area,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as vacant_count,
                 COALESCE(SUM(CASE WHEN status = ? THEN area_sqm ELSE 0 END), 0) as vacant_area',
                ['vacant', 'vacant']
            )
            ->groupBy('unit_type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->unit_type => [
                    'total'              => (int) $row->total,
                    'occupied'           => (int) $row->total - (int) $row->vacant_count,
                    'vacant'             => (int) $row->vacant_count,
                    'vacancy_rate_pct'   => $row->total > 0
                        ? round($row->vacant_count / $row->total * 100, 2)
                        : 0,
                    'total_area_sqm'  => (float) $row->total_area,
                    'vacant_area_sqm' => (float) $row->vacant_area,
                ],
            ]);

        $total       = (int) ($totals->total ?? 0);
        $vacantCount = (int) ($totals->vacant_count ?? 0);
        $totalArea   = (float) ($totals->total_area ?? 0);
        $vacantArea  = (float) ($totals->vacant_area ?? 0);

        return [
            'total_units'              => $total,
            'occupied_units'           => $total - $vacantCount,
            'vacant_units'             => $vacantCount,
            'total_area_sqm'           => $totalArea,
            'vacant_area_sqm'          => $vacantArea,
            'overall_vacancy_rate_pct' => $total > 0
                ? round($vacantCount / $total * 100, 2)
                : 0,
            'by_unit_type' => $byType,
        ];
    }

    public function getExpiringContracts(int $organizationId, int $withinDays = 90): Collection
    {
        return LeaseContract::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($withinDays)->toDateString())
            ->with(['rentalUnit', 'options'])
            ->orderBy('end_date')
            ->limit(200)
            ->get();
    }

    public function getUpcomingEscalations(int $organizationId, int $withinDays = 30): Collection
    {
        return ContractCondition::whereHas('contract', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'active'))
            ->where('is_active', true)
            ->whereNotNull('next_escalation_date')
            ->where('next_escalation_date', '<=', now()->addDays($withinDays)->toDateString())
            ->with('contract.rentalUnit')
            ->orderBy('next_escalation_date')
            ->limit(200)
            ->get();
    }

    // -------------------------------------------------------------------------
    // IFRS 16 — Right-of-Use Asset & Lease Liability
    // -------------------------------------------------------------------------

    /**
     * Generate (or regenerate) the IFRS 16 amortisation schedule for a lessee
     * contract and persist it in re_ifrs16_schedules.
     *
     * SAP RE-FX equivalent: IFRS16 commencement date + IBR trigger.
     *
     * @param  LeaseContract $contract   Lessee-side contract (contract_type = lease_in).
     * @param  float         $ibrPercent Incremental Borrowing Rate as annual percentage (e.g. 5.5 for 5.5%).
     * @param  string|null   $commencementDate Defaults to contract start_date.
     * @return array{rou_asset: float, rows: Ifrs16Schedule[]}
     */
    public function generateIfrs16Schedule(
        LeaseContract $contract,
        float $ibrPercent,
        ?string $commencementDate = null
    ): array {
        if ($contract->contract_type !== 'lease_in') {
            throw new \InvalidArgumentException('IFRS 16 applies to lessee contracts (contract_type = lease_in) only.');
        }

        $start = \Carbon\Carbon::parse($commencementDate ?? $contract->start_date);
        $end   = \Carbon\Carbon::parse($contract->end_date);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('Contract end_date must be after the commencement date.');
        }

        // Monthly base rent from the active condition (first base_rent condition).
        $monthlyPayment = (float) ($contract->conditions()
            ->where('condition_type', 'base_rent')
            ->where('is_active', true)
            ->value('amount') ?? 0);

        if ($monthlyPayment <= 0) {
            throw new \InvalidArgumentException('No active base_rent condition found on the contract.');
        }

        // Monthly IBR = (1 + annual_ibr/100)^(1/12) - 1
        $monthlyIbr = (1 + $ibrPercent / 100) ** (1 / 12) - 1;

        // Total lease term in months.
        $totalMonths = (int) $start->diffInMonths($end);

        // Present value of an annuity: PV = PMT × [1 - (1+r)^-n] / r
        $pvFactor = $monthlyIbr > 0
            ? (1 - (1 + $monthlyIbr) ** -$totalMonths) / $monthlyIbr
            : (float) $totalMonths;

        $rouAsset = round($monthlyPayment * $pvFactor, 4);
        $monthlyDepreciation = $totalMonths > 0 ? round($rouAsset / $totalMonths, 4) : 0.0;

        // Delete any previously generated schedule for this contract.
        Ifrs16Schedule::where('contract_id', $contract->id)->delete();

        $rows    = [];
        $openingLiability = $rouAsset;
        $rouBookValue     = $rouAsset;

        $periodDate = $start->copy()->startOfMonth();

        for ($i = 0; $i < $totalMonths; $i++) {
            $interest          = round($openingLiability * $monthlyIbr, 4);
            $principal         = round($monthlyPayment - $interest, 4);
            $closingLiability  = round($openingLiability - $principal, 4);
            $rouBookValue      = round($rouBookValue - $monthlyDepreciation, 4);

            // Clamp rounding drift on final period.
            if ($i === $totalMonths - 1) {
                $closingLiability = 0.0;
                $rouBookValue     = 0.0;
            }

            $rows[] = Ifrs16Schedule::create([
                'contract_id'       => $contract->id,
                'period_date'       => $periodDate->toDateString(),
                'opening_liability' => $openingLiability,
                'interest_expense'  => $interest,
                'lease_payment'     => $monthlyPayment,
                'principal_reduction' => $principal,
                'closing_liability' => $closingLiability,
                'rou_depreciation'  => $monthlyDepreciation,
                'rou_book_value'    => $rouBookValue,
                'gl_posted'         => false,
            ]);

            $openingLiability = $closingLiability;
            $periodDate->addMonth();
        }

        // Persist IFRS 16 summary fields on the contract.
        $contract->update([
            'ibr_percent'             => $ibrPercent,
            'rou_asset_amount'        => $rouAsset,
            'lease_liability_amount'  => $rouAsset,
            'ifrs16_commencement_date'=> $start->toDateString(),
            'ifrs16_applied'          => true,
        ]);

        return [
            'rou_asset'       => $rouAsset,
            'total_months'    => $totalMonths,
            'monthly_payment' => $monthlyPayment,
            'ibr_percent'     => $ibrPercent,
            'rows'            => $rows,
        ];
    }

    /**
     * Return the persisted IFRS 16 schedule for a contract.
     */
    public function getIfrs16Schedule(LeaseContract $contract): array
    {
        $rows = Ifrs16Schedule::where('contract_id', $contract->id)
            ->orderBy('period_date')
            ->get();

        return [
            'contract_id'             => $contract->id,
            'rou_asset_amount'        => (float) $contract->rou_asset_amount,
            'lease_liability_amount'  => (float) $contract->lease_liability_amount,
            'ibr_percent'             => (float) $contract->ibr_percent,
            'ifrs16_commencement_date'=> $contract->ifrs16_commencement_date,
            'ifrs16_applied'          => (bool) $contract->ifrs16_applied,
            'schedule'                => $rows->map(fn ($r) => [
                'period_date'         => $r->period_date,
                'opening_liability'   => (float) $r->opening_liability,
                'interest_expense'    => (float) $r->interest_expense,
                'lease_payment'       => (float) $r->lease_payment,
                'principal_reduction' => (float) $r->principal_reduction,
                'closing_liability'   => (float) $r->closing_liability,
                'rou_depreciation'    => (float) $r->rou_depreciation,
                'rou_book_value'      => (float) $r->rou_book_value,
                'gl_posted'           => $r->gl_posted,
            ])->values()->toArray(),
        ];
    }
}
