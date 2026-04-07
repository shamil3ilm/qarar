<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\InternalOrder;
use App\Models\Accounting\InternalOrderSettlement;
use App\Models\Accounting\ProfitCenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InternalOrderService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function index(array $filters): LengthAwarePaginator
    {
        $query = InternalOrder::query()->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }

        if (!empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', (int) $filters['cost_center_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->with(['costCenter:id,code,name', 'responsibleUser:id,name'])->paginate($perPage);
    }

    public function store(array $data): InternalOrder
    {
        return DB::transaction(function () use ($data): InternalOrder {
            return InternalOrder::create(array_merge($data, [
                'status'           => InternalOrder::STATUS_CREATED,
                'committed_amount' => 0,
                'actual_amount'    => 0,
            ]));
        });
    }

    // ----------------------------------------------------------------
    // Status Transitions
    // ----------------------------------------------------------------

    /**
     * Transition status from created -> released.
     */
    public function release(InternalOrder $order): InternalOrder
    {
        return DB::transaction(function () use ($order): InternalOrder {
            if (!$order->isCreated()) {
                throw new InvalidArgumentException(
                    "Only orders in 'created' status can be released. Current status: [{$order->status}]."
                );
            }

            $order->update(['status' => InternalOrder::STATUS_RELEASED]);

            return $order->fresh();
        });
    }

    /**
     * Transition status from released -> technically_completed.
     * No more cost postings allowed after this point.
     */
    public function technicallyComplete(InternalOrder $order): InternalOrder
    {
        return DB::transaction(function () use ($order): InternalOrder {
            if (!$order->isReleased()) {
                throw new InvalidArgumentException(
                    "Only released orders can be technically completed. Current status: [{$order->status}]."
                );
            }

            $order->update(['status' => InternalOrder::STATUS_TECHNICALLY_COMPLETED]);

            return $order->fresh();
        });
    }

    /**
     * Transition status from technically_completed -> closed.
     * Settlement must have been run before closing.
     */
    public function close(InternalOrder $order): InternalOrder
    {
        return DB::transaction(function () use ($order): InternalOrder {
            if (!$order->isTechnicallyCompleted()) {
                throw new InvalidArgumentException(
                    "Only technically completed orders can be closed. Current status: [{$order->status}]."
                );
            }

            $order->update(['status' => InternalOrder::STATUS_CLOSED]);

            return $order->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Settlement
    // ----------------------------------------------------------------

    /**
     * Distribute actual costs from the internal order to its settlement receivers.
     *
     * For each settlement rule the method creates a balancing journal entry:
     *   Dr: receiver GL account (or cost center GL)
     *   Cr: internal order cost center GL account
     *
     * Total settlement percentages must sum to 100.
     */
    public function settle(InternalOrder $order): InternalOrder
    {
        return DB::transaction(function () use ($order): InternalOrder {
            if (!$order->isReleased() && !$order->isTechnicallyCompleted()) {
                throw new InvalidArgumentException(
                    'Only released or technically completed orders can be settled.'
                );
            }

            $actualAmount = (float) $order->actual_amount;

            if ($actualAmount <= 0) {
                throw new InvalidArgumentException(
                    'Internal order has no actual costs to settle.'
                );
            }

            $settlements = $order->settlements()->get();

            if ($settlements->isEmpty()) {
                throw new InvalidArgumentException(
                    'No settlement rules defined for this internal order.'
                );
            }

            $totalPct = $settlements->sum('settlement_percentage');

            if (abs($totalPct - 100.0) > 0.01) {
                throw new InvalidArgumentException(
                    "Settlement percentages must total 100% (current total: {$totalPct}%)."
                );
            }

            // Resolve source GL account from the order's cost center
            $sourceCostCenter = $order->costCenter()->with('glAccount')->first();

            if ($sourceCostCenter === null || $sourceCostCenter->gl_account_id === null) {
                throw new InvalidArgumentException(
                    'The internal order cost center must have a GL account assigned for settlement.'
                );
            }

            // Determine fiscal year for the entry date
            $entryDate   = now()->toDateString();
            $fiscalYear  = FiscalYear::forDate($order->organization_id, $entryDate);

            foreach ($settlements as $settlement) {
                $settleAmount = round($actualAmount * ((float) $settlement->settlement_percentage / 100), 4);

                if ($settleAmount <= 0) {
                    continue;
                }

                $receiverAccountId = $this->resolveReceiverAccountId($settlement);

                $this->journalService->createEntry(
                    [
                        'organization_id' => $order->organization_id,
                        'entry_date'      => $entryDate,
                        'fiscal_year_id'  => $fiscalYear?->id,
                        'description'     => "IO Settlement: {$order->order_number} → {$settlement->receiver_type}:{$settlement->receiver_id}",
                        'source_type'     => InternalOrder::class,
                        'source_id'       => $order->id,
                        'currency_code'   => 'SAR',
                        'exchange_rate'   => 1,
                    ],
                    [
                        [
                            'account_id'     => $sourceCostCenter->gl_account_id,
                            'description'    => "IO settlement credit — {$order->order_number}",
                            'debit'          => 0,
                            'credit'         => $settleAmount,
                            'cost_center_id' => $sourceCostCenter->id,
                        ],
                        [
                            'account_id'     => $receiverAccountId,
                            'description'    => "IO settlement debit — {$settlement->receiver_type}:{$settlement->receiver_id}",
                            'debit'          => $settleAmount,
                            'credit'         => 0,
                        ],
                    ]
                );
            }

            return $order->fresh(['settlements', 'costCenter:id,code,name']);
        });
    }

    // ----------------------------------------------------------------
    // Budget Availability
    // ----------------------------------------------------------------

    /**
     * Check whether posting $amount to the order would exceed the budget.
     *
     * @throws \DomainException when the budget would be exceeded
     */
    public function checkBudgetAvailability(InternalOrder $order, float $amount): void
    {
        if ($order->budget_amount === null) {
            // No budget set — unrestricted
            return;
        }

        $remaining = (float) $order->budget_amount - (float) $order->actual_amount;

        if ($amount > $remaining) {
            throw new \DomainException(
                "Budget exceeded for internal order {$order->order_number}: "
                . "remaining budget is {$remaining}, requested {$amount}."
            );
        }
    }

    /**
     * Return a structured budget status snapshot for the order.
     *
     * @return array{budget_amount: float|null, actual_amount: float, committed_amount: float, available_amount: float|null, utilization_pct: float|null}
     */
    public function getBudgetStatus(InternalOrder $order): array
    {
        $budget  = $order->budget_amount !== null ? (float) $order->budget_amount : null;
        $actual  = (float) $order->actual_amount;

        return [
            'budget_amount'    => $budget,
            'actual_amount'    => $actual,
            'committed_amount' => 0, // placeholder for future commitments
            'available_amount' => $budget !== null ? ($budget - $actual) : null,
            'utilization_pct'  => ($budget !== null && $budget > 0)
                ? round(($actual / $budget) * 100, 2)
                : null,
        ];
    }

    // ----------------------------------------------------------------
    // Variance Report
    // ----------------------------------------------------------------

    /**
     * Return a plan-vs-actual variance report for the order for a given fiscal year.
     *
     * @return array{order_id: int, order_number: string, fiscal_year: int, budget_amount: float|null, actual_amount: float, variance: float|null, variance_pct: float|null, settlements: array<int, mixed>}
     */
    public function getVarianceReport(InternalOrder $order, int $fiscalYear): array
    {
        $budget  = $order->budget_amount !== null ? (float) $order->budget_amount : null;
        $actual  = (float) $order->actual_amount;
        $variance = $budget !== null ? ($budget - $actual) : null;

        $settlements = $order->settlements()
            ->get()
            ->toArray();

        return [
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'fiscal_year'   => $fiscalYear,
            'budget_amount' => $budget,
            'actual_amount' => $actual,
            'variance'      => $variance,
            'variance_pct'  => ($budget !== null && $budget > 0)
                ? round(($variance / $budget) * 100, 2)
                : null,
            'settlements'   => $settlements,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Resolve the GL account ID for the settlement receiver.
     */
    private function resolveReceiverAccountId(InternalOrderSettlement $settlement): int
    {
        return match ($settlement->receiver_type) {
            InternalOrderSettlement::RECEIVER_COST_CENTER => $this->glAccountForCostCenter((int) $settlement->receiver_id),
            InternalOrderSettlement::RECEIVER_GL_ACCOUNT  => (int) $settlement->receiver_id,
            InternalOrderSettlement::RECEIVER_PROFIT_CENTER => $this->glAccountForProfitCenter((int) $settlement->receiver_id),
            default => throw new InvalidArgumentException(
                "Unsupported receiver type [{$settlement->receiver_type}] for settlement."
            ),
        };
    }

    private function glAccountForCostCenter(int $costCenterId): int
    {
        $costCenter = CostCenter::withoutGlobalScopes()->findOrFail($costCenterId);

        if ($costCenter->gl_account_id === null) {
            throw new InvalidArgumentException(
                "Cost center [{$costCenter->code}] has no GL account assigned."
            );
        }

        return (int) $costCenter->gl_account_id;
    }

    private function glAccountForProfitCenter(int $profitCenterId): int
    {
        $profitCenter = ProfitCenter::withoutGlobalScopes()->findOrFail($profitCenterId);

        if ($profitCenter->gl_account_id === null) {
            throw new InvalidArgumentException(
                "Profit center [{$profitCenter->code}] has no GL account assigned."
            );
        }

        return (int) $profitCenter->gl_account_id;
    }
}
