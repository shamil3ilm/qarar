<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankPosition;
use App\Models\Accounting\LiquidityPlan;
use App\Models\Accounting\LiquidityPlanLine;
use App\Models\Accounting\TreasuryInvestment;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\PaymentReceived;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private JournalService $journalService,
    ) {}

    // -------------------------------------------------------------------------
    // Investments
    // -------------------------------------------------------------------------

    /**
     * Record a new treasury investment and create the opening journal entry.
     * Dr: Investment GL Account
     * Cr: Bank Account
     */
    public function createInvestment(array $data): TreasuryInvestment
    {
        return DB::transaction(function () use ($data): TreasuryInvestment {
            $orgId = (int) $data['organization_id'];

            if (empty($data['instrument_number'])) {
                $count = TreasuryInvestment::where('organization_id', $orgId)->withTrashed()->count() + 1;
                $data['instrument_number'] = 'INV-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
            }

            // Calculate maturity value (simple interest approximation for display)
            $principal     = (float) $data['principal_amount'];
            $rate          = (float) $data['interest_rate'];
            $investDate    = Carbon::parse($data['investment_date']);
            $maturityDate  = Carbon::parse($data['maturity_date']);
            $days          = $investDate->diffInDays($maturityDate);
            $maturityValue = $principal + ($principal * $rate / 100 * $days / 365);

            $investment = TreasuryInvestment::create(array_merge($data, [
                'accrued_interest' => 0,
                'maturity_value'   => round($maturityValue, 4),
                'status'           => TreasuryInvestment::STATUS_ACTIVE,
            ]));

            // Journal entry: Dr Investment / Cr Bank
            $this->journalService->create([
                'organization_id' => $orgId,
                'reference'       => $investment->instrument_number,
                'description'     => "Treasury investment — {$investment->instrument_number}",
                'entry_date'      => $investment->investment_date->toDateString(),
                'currency_code'   => $investment->currency_code,
                'created_by'      => $data['created_by'],
            ], [
                [
                    'account_code' => '1300', // Investments
                    'debit'        => $principal,
                    'credit'       => 0,
                    'description'  => "Investment in {$investment->counterparty}",
                ],
                [
                    'account_code' => '1020', // Bank
                    'debit'        => 0,
                    'credit'       => $principal,
                    'description'  => "Payment for investment — {$investment->instrument_number}",
                ],
            ]);

            return $investment->fresh();
        });
    }

    /**
     * Calculate accrued interest for an investment as of a given date (simple interest).
     */
    public function accrueInterest(TreasuryInvestment $investment, string $asOfDate): float
    {
        if ($investment->status !== TreasuryInvestment::STATUS_ACTIVE) {
            throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                'message' => 'Only active investments can have interest accrued.',
            ]);
        }

        $from  = $investment->investment_date;
        $to    = Carbon::parse($asOfDate);
        $days  = $from->diffInDays($to);

        $accrued = round(
            (float) $investment->principal_amount
            * (float) $investment->interest_rate / 100
            * $days / 365,
            4
        );

        DB::transaction(function () use ($investment, $accrued, $asOfDate): void {
            $investment->update(['accrued_interest' => $accrued]);

            // Dr: Accrued Interest Receivable / Cr: Interest Income
            $this->journalService->create([
                'organization_id' => $investment->organization_id,
                'reference'       => $investment->instrument_number,
                'description'     => "Interest accrual — {$investment->instrument_number}",
                'entry_date'      => $asOfDate,
                'currency_code'   => $investment->currency_code,
                'created_by'      => $investment->created_by,
            ], [
                [
                    'account_code' => '1310', // Accrued Interest Receivable
                    'debit'        => $accrued,
                    'credit'       => 0,
                    'description'  => "Accrued interest on {$investment->instrument_number}",
                ],
                [
                    'account_code' => '4100', // Interest Income
                    'debit'        => 0,
                    'credit'       => $accrued,
                    'description'  => "Interest income — {$investment->instrument_number}",
                ],
            ]);
        });

        return $accrued;
    }

    /**
     * Process investment maturity: return principal + interest via journal entries.
     */
    public function mature(TreasuryInvestment $investment): void
    {
        DB::transaction(function () use ($investment): void {
            if ($investment->status !== TreasuryInvestment::STATUS_ACTIVE) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Only active investments can be matured.',
                ]);
            }

            $principal = (float) $investment->principal_amount;
            $interest  = (float) $investment->accrued_interest;
            $total     = $principal + $interest;
            $matDate   = $investment->maturity_date->toDateString();

            $investment->update(['status' => TreasuryInvestment::STATUS_MATURED]);

            // Dr: Bank (principal + interest) / Cr: Investment + Accrued Interest Receivable
            $this->journalService->create([
                'organization_id' => $investment->organization_id,
                'reference'       => $investment->instrument_number,
                'description'     => "Maturity receipt — {$investment->instrument_number}",
                'entry_date'      => $matDate,
                'currency_code'   => $investment->currency_code,
                'created_by'      => $investment->created_by,
            ], [
                [
                    'account_code' => '1020', // Bank
                    'debit'        => $total,
                    'credit'       => 0,
                    'description'  => "Principal + interest on maturity",
                ],
                [
                    'account_code' => '1300', // Investment
                    'debit'        => 0,
                    'credit'       => $principal,
                    'description'  => "Return of principal — {$investment->instrument_number}",
                ],
                [
                    'account_code' => '1310', // Accrued Interest Receivable
                    'debit'        => 0,
                    'credit'       => $interest,
                    'description'  => "Interest collected on maturity",
                ],
            ]);
        });
    }

    /**
     * Pre-liquidate an investment before maturity, with an optional early-redemption penalty.
     */
    public function preLiquidate(
        TreasuryInvestment $investment,
        string $liquidationDate,
        float $earlyRedemptionPenalty = 0.0,
    ): void {
        DB::transaction(function () use ($investment, $liquidationDate, $earlyRedemptionPenalty): void {
            if ($investment->status !== TreasuryInvestment::STATUS_ACTIVE) {
                throw ApiException::fromError(ErrorCodes::BIZ_INVALID_STATUS_TRANSITION, [
                    'message' => 'Only active investments can be pre-liquidated.',
                ]);
            }

            $principal  = (float) $investment->principal_amount;
            $interest   = (float) $investment->accrued_interest;
            $netReceipt = $principal + $interest - $earlyRedemptionPenalty;

            $investment->update([
                'status'          => TreasuryInvestment::STATUS_PRE_LIQUIDATED,
                'accrued_interest' => $interest,
            ]);

            $lines = [
                [
                    'account_code' => '1020',
                    'debit'        => max(0, $netReceipt),
                    'credit'       => 0,
                    'description'  => "Pre-liquidation receipt",
                ],
                [
                    'account_code' => '1300',
                    'debit'        => 0,
                    'credit'       => $principal,
                    'description'  => "Return of principal on pre-liquidation",
                ],
            ];

            if ($interest > 0) {
                $lines[] = [
                    'account_code' => '1310',
                    'debit'        => 0,
                    'credit'       => $interest,
                    'description'  => "Accrued interest on pre-liquidation",
                ];
            }

            if ($earlyRedemptionPenalty > 0) {
                $lines[] = [
                    'account_code' => '6900', // Bank charges / penalties
                    'debit'        => $earlyRedemptionPenalty,
                    'credit'       => 0,
                    'description'  => "Early redemption penalty",
                ];
            }

            $this->journalService->create([
                'organization_id' => $investment->organization_id,
                'reference'       => $investment->instrument_number,
                'description'     => "Pre-liquidation — {$investment->instrument_number}",
                'entry_date'      => $liquidationDate,
                'currency_code'   => $investment->currency_code,
                'created_by'      => $investment->created_by,
            ], $lines);
        });
    }

    // -------------------------------------------------------------------------
    // Bank Positions
    // -------------------------------------------------------------------------

    /**
     * Compute and persist bank positions for all accounts of an organisation on a date.
     */
    public function calculateBankPosition(int $orgId, string $date): array
    {
        $accounts = BankAccount::where('organization_id', $orgId)->get();
        $results  = [];

        foreach ($accounts as $account) {
            // Book balance = sum of cleared journal lines for this account's GL
            $bookBalance = (float) ($account->current_balance ?? 0);

            // Available = book balance minus pending outflows (AP not yet posted)
            $pendingPayments = (float) PaymentMade::where('organization_id', $orgId)
                ->where('bank_account_id', $account->id)
                ->whereIn('status', ['pending'])
                ->sum('amount');

            $availableBalance = $bookBalance - $pendingPayments;

            // Projected = available + known future inflows (open invoices due within 30 days)
            $futureInflows = (float) \App\Models\Sales\Invoice::where('organization_id', $orgId)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->where('due_date', '<=', Carbon::parse($date)->addDays(30)->toDateString())
                ->sum('amount_due');

            $projectedBalance = $availableBalance + $futureInflows;

            $position = BankPosition::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'bank_account_id' => $account->id,
                    'position_date'   => $date,
                ],
                [
                    'book_balance'      => $bookBalance,
                    'available_balance' => $availableBalance,
                    'projected_balance' => $projectedBalance,
                    'currency_code'     => $account->currency_code ?? 'SAR',
                ]
            );

            $results[] = $position;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Liquidity Plans
    // -------------------------------------------------------------------------

    /**
     * Create a new liquidity plan (header + optional lines).
     */
    public function createLiquidityPlan(array $data): LiquidityPlan
    {
        return DB::transaction(function () use ($data): LiquidityPlan {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $plan = LiquidityPlan::create($data);

            foreach ($lines as $line) {
                $plan->lines()->create($line);
            }

            return $plan->fresh(['lines']);
        });
    }

    /**
     * Populate actual_amount on each plan line from real transactions.
     */
    public function updateActuals(LiquidityPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            foreach ($plan->lines as $line) {
                $periodStart = $line->period_date;
                $periodEnd   = match ($plan->granularity) {
                    'daily'   => $periodStart,
                    'weekly'  => $periodStart->copy()->addDays(6),
                    'monthly' => $periodStart->copy()->endOfMonth(),
                    default   => $periodStart,
                };

                if ($line->flow_type === 'inflow') {
                    $actual = (float) \App\Models\Sales\PaymentReceived::where('organization_id', $plan->organization_id)
                        ->whereBetween('payment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                        ->sum('amount');
                } else {
                    $actual = (float) PaymentMade::where('organization_id', $plan->organization_id)
                        ->whereBetween('payment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                        ->sum('amount');
                }

                $line->update(['actual_amount' => $actual]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Summaries
    // -------------------------------------------------------------------------

    /**
     * Return current positions across all accounts + active investments.
     */
    public function getPositionSummary(int $orgId): array
    {
        $today = now()->toDateString();

        $positions   = $this->calculateBankPosition($orgId, $today);
        $investments = TreasuryInvestment::where('organization_id', $orgId)
            ->where('status', TreasuryInvestment::STATUS_ACTIVE)
            ->get(['id', 'instrument_number', 'instrument_type', 'counterparty', 'principal_amount', 'accrued_interest', 'maturity_date', 'currency_code']);

        $totalBook      = collect($positions)->sum('book_balance');
        $totalAvailable = collect($positions)->sum('available_balance');
        $totalInvested  = $investments->sum('principal_amount');

        return [
            'as_of_date'           => $today,
            'bank_positions'       => $positions,
            'active_investments'   => $investments,
            'summary' => [
                'total_book_balance'      => round($totalBook, 4),
                'total_available_balance' => round($totalAvailable, 4),
                'total_invested'          => round((float) $totalInvested, 4),
                'total_liquidity'         => round($totalAvailable + (float) $totalInvested, 4),
            ],
        ];
    }

    /**
     * Return investments maturing within the next N days.
     */
    public function getMaturingInvestments(int $orgId, int $daysAhead = 30): Collection
    {
        return TreasuryInvestment::where('organization_id', $orgId)
            ->where('status', TreasuryInvestment::STATUS_ACTIVE)
            ->where('maturity_date', '<=', now()->addDays($daysAhead)->toDateString())
            ->orderBy('maturity_date')
            ->get();
    }
}
