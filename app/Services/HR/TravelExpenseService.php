<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\PerDiemRate;
use App\Models\HR\TravelExpenseClaim;
use App\Models\HR\TravelExpenseLine;
use App\Models\HR\TravelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TravelExpenseService
{
    // ---------------------------------------------------------------
    // Per Diem Rates
    // ---------------------------------------------------------------

    public function storePerDiemRate(array $data): PerDiemRate
    {
        return PerDiemRate::create($data);
    }

    /**
     * Calculate per diem breakdown for a given destination and number of days.
     *
     * @return array{
     *   country: string,
     *   city: string|null,
     *   days: int,
     *   daily_allowance: float,
     *   daily_meals: float,
     *   daily_total: float,
     *   total_allowance: float,
     *   total_meals: float,
     *   grand_total: float,
     *   currency_code: string,
     *   rate_found: bool
     * }
     */
    public function calculatePerDiem(int $orgId, string $country, ?string $city, int $days): array
    {
        // Try city-specific rate first, then country-wide
        $rate = PerDiemRate::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('destination_country', $country)
            ->where('destination_city', $city)
            ->first();

        if ($rate === null && $city !== null) {
            $rate = PerDiemRate::withoutGlobalScope('organization')
                ->where('organization_id', $orgId)
                ->where('is_active', true)
                ->where('destination_country', $country)
                ->whereNull('destination_city')
                ->first();
        }

        if ($rate === null) {
            return [
                'country'         => $country,
                'city'            => $city,
                'days'            => $days,
                'daily_allowance' => 0.0,
                'daily_meals'     => 0.0,
                'daily_total'     => 0.0,
                'total_allowance' => 0.0,
                'total_meals'     => 0.0,
                'grand_total'     => 0.0,
                'currency_code'   => 'SAR',
                'rate_found'      => false,
            ];
        }

        $dailyAllowance = (float) $rate->daily_allowance;
        $dailyMeals     = $rate->getTotalMealAllowance();
        $dailyTotal     = $dailyAllowance + $dailyMeals;

        return [
            'country'         => $country,
            'city'            => $city,
            'days'            => $days,
            'daily_allowance' => $dailyAllowance,
            'daily_meals'     => $dailyMeals,
            'daily_total'     => $dailyTotal,
            'total_allowance' => round($dailyAllowance * $days, 4),
            'total_meals'     => round($dailyMeals * $days, 4),
            'grand_total'     => round($dailyTotal * $days, 4),
            'currency_code'   => $rate->currency_code,
            'rate_found'      => true,
        ];
    }

    // ---------------------------------------------------------------
    // Travel Requests
    // ---------------------------------------------------------------

    public function createRequest(array $data): TravelRequest
    {
        return DB::transaction(function () use ($data): TravelRequest {
            $requestNumber = $this->generateRequestNumber($data['organization_id']);

            // Estimate per diem if not provided
            $estimatedCost = $data['estimated_cost'] ?? 0;
            if ($estimatedCost == 0) {
                $departure = new \DateTime($data['departure_date']);
                $return    = new \DateTime($data['return_date']);
                $days      = (int) $departure->diff($return)->days + 1;

                $perDiem = $this->calculatePerDiem(
                    $data['organization_id'],
                    $data['destination_country'],
                    $data['destination_city'] ?? null,
                    $days
                );

                $estimatedCost = $perDiem['grand_total'];
            }

            return TravelRequest::create([
                'organization_id'    => $data['organization_id'],
                'employee_id'        => $data['employee_id'],
                'request_number'     => $requestNumber,
                'purpose'            => $data['purpose'],
                'departure_date'     => $data['departure_date'],
                'return_date'        => $data['return_date'],
                'destination_country' => $data['destination_country'],
                'destination_city'   => $data['destination_city'] ?? null,
                'travel_type'        => $data['travel_type'] ?? TravelRequest::TYPE_DOMESTIC,
                'estimated_cost'     => $estimatedCost,
                'advance_requested'  => $data['advance_requested'] ?? 0,
                'advance_approved'   => 0,
                'status'             => TravelRequest::STATUS_DRAFT,
                'created_by'         => $data['created_by'],
            ]);
        });
    }

    public function submit(TravelRequest $request): void
    {
        if (!$request->isDraft()) {
            throw new \InvalidArgumentException('Only draft requests can be submitted.');
        }

        $request->update(['status' => TravelRequest::STATUS_SUBMITTED]);
    }

    public function approve(TravelRequest $request, float $advanceApproved): void
    {
        if ($request->status !== TravelRequest::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('Only submitted requests can be approved.');
        }

        $request->update([
            'status'           => TravelRequest::STATUS_APPROVED,
            'advance_approved' => $advanceApproved,
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);
    }

    public function reject(TravelRequest $request, string $reason): void
    {
        if ($request->status !== TravelRequest::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('Only submitted requests can be rejected.');
        }

        $request->update([
            'status'           => TravelRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    // ---------------------------------------------------------------
    // Expense Claims
    // ---------------------------------------------------------------

    public function createClaim(array $data): TravelExpenseClaim
    {
        return DB::transaction(function () use ($data): TravelExpenseClaim {
            $claimNumber = $this->generateClaimNumber($data['organization_id']);

            return TravelExpenseClaim::create([
                'organization_id'   => $data['organization_id'],
                'travel_request_id' => $data['travel_request_id'] ?? null,
                'employee_id'       => $data['employee_id'],
                'claim_number'      => $claimNumber,
                'claim_date'        => $data['claim_date'] ?? now()->toDateString(),
                'total_claimed'     => 0,
                'advance_paid'      => $data['advance_paid'] ?? 0,
                'amount_reimbursable' => 0,
                'amount_deductible'   => 0,
                'status'            => TravelExpenseClaim::STATUS_DRAFT,
                'created_by'        => $data['created_by'],
            ]);
        });
    }

    public function addLine(TravelExpenseClaim $claim, array $data): TravelExpenseLine
    {
        if (!$claim->isDraft()) {
            throw new \InvalidArgumentException('Lines can only be added to draft claims.');
        }

        return DB::transaction(function () use ($claim, $data): TravelExpenseLine {
            $amount       = (float) $data['amount'];
            $exchangeRate = (float) ($data['exchange_rate'] ?? 1.0);
            $amountBase   = round($amount * $exchangeRate, 4);
            $mileageKm    = isset($data['mileage_km']) ? (float) $data['mileage_km'] : null;

            // For mileage, calculate amount from km * mileage_rate
            if ($data['expense_category'] === TravelExpenseLine::CATEGORY_MILEAGE && $mileageKm !== null) {
                $mileageRate = $this->getMileageRate($claim->organization_id, $data['currency_code'] ?? 'SAR');
                $amount      = round($mileageKm * $mileageRate, 4);
                $amountBase  = round($amount * $exchangeRate, 4);
            }

            // Check policy compliance
            [$policyLimit, $withinPolicy] = $this->checkPolicy(
                $claim,
                $data['expense_category'],
                $amountBase,
                $data['expense_date']
            );

            $line = TravelExpenseLine::create([
                'claim_id'                => $claim->id,
                'expense_date'            => $data['expense_date'],
                'expense_category'        => $data['expense_category'],
                'description'             => $data['description'] ?? null,
                'amount'                  => $amount,
                'mileage_km'              => $mileageKm,
                'currency_code'           => $data['currency_code'] ?? 'SAR',
                'exchange_rate'           => $exchangeRate,
                'amount_in_base_currency' => $amountBase,
                'receipt_reference'       => $data['receipt_reference'] ?? null,
                'receipt_attached'        => $data['receipt_attached'] ?? false,
                'policy_limit'            => $policyLimit,
                'within_policy'           => $withinPolicy,
            ]);

            $this->recalculateClaim($claim);

            return $line;
        });
    }

    public function submitClaim(TravelExpenseClaim $claim): void
    {
        if (!$claim->isDraft()) {
            throw new \InvalidArgumentException('Only draft claims can be submitted.');
        }

        if ($claim->lines()->count() === 0) {
            throw new \InvalidArgumentException('Cannot submit a claim with no expense lines.');
        }

        $claim->update(['status' => TravelExpenseClaim::STATUS_SUBMITTED]);
    }

    public function approveClaim(TravelExpenseClaim $claim): void
    {
        if ($claim->status !== TravelExpenseClaim::STATUS_SUBMITTED) {
            throw new \InvalidArgumentException('Only submitted claims can be approved.');
        }

        DB::transaction(function () use ($claim): void {
            $lines = $claim->lines()->get();

            $reimbursable = 0.0;
            $deductible   = 0.0;

            foreach ($lines as $line) {
                $baseAmount = (float) $line->amount_in_base_currency;
                if ($line->within_policy) {
                    $reimbursable += $baseAmount;
                } else {
                    $limit = (float) ($line->policy_limit ?? 0);
                    if ($limit > 0) {
                        $reimbursable += $limit;
                        $deductible   += max(0, $baseAmount - $limit);
                    } else {
                        $deductible += $baseAmount;
                    }
                }
            }

            $claim->update([
                'status'              => TravelExpenseClaim::STATUS_APPROVED,
                'amount_reimbursable' => round($reimbursable, 4),
                'amount_deductible'   => round($deductible, 4),
                'approved_by'         => auth()->id(),
            ]);
        });
    }

    public function processClaim(TravelExpenseClaim $claim): void
    {
        if (!$claim->isApproved()) {
            throw new \InvalidArgumentException('Only approved claims can be processed for payment.');
        }

        DB::transaction(function () use ($claim): void {
            // Mark as paid
            $claim->update(['status' => TravelExpenseClaim::STATUS_PAID]);

            Log::info('Travel expense claim processed', [
                'claim_id'            => $claim->id,
                'claim_number'        => $claim->claim_number,
                'amount_reimbursable' => $claim->amount_reimbursable,
                'net_reimbursable'    => $claim->getNetReimbursable(),
                'employee_id'         => $claim->employee_id,
            ]);

            // Journal entry creation would be integrated with JournalService here.
            // Dr: Travel Expense Account, Cr: Accounts Payable / Cash
        });
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function generateRequestNumber(int $orgId): string
    {
        $last = TravelRequest::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->withTrashed()
            ->orderBy('id', 'desc')
            ->value('request_number');

        $seq = $last !== null ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('TRV-%s-%06d', date('Y'), $seq);
    }

    private function generateClaimNumber(int $orgId): string
    {
        $last = TravelExpenseClaim::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->withTrashed()
            ->orderBy('id', 'desc')
            ->value('claim_number');

        $seq = $last !== null ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('TEC-%s-%06d', date('Y'), $seq);
    }

    private function getMileageRate(int $orgId, string $currency): float
    {
        $rate = PerDiemRate::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('mileage_rate', '>', 0)
            ->where('currency_code', $currency)
            ->orderBy('mileage_rate', 'desc')
            ->value('mileage_rate');

        return $rate !== null ? (float) $rate : 0.0;
    }

    /**
     * Check expense against policy limits.
     *
     * @return array{0: float|null, 1: bool}
     */
    private function checkPolicy(
        TravelExpenseClaim $claim,
        string $category,
        float $amountBase,
        string $expenseDate
    ): array {
        if ($category === TravelExpenseLine::CATEGORY_PER_DIEM) {
            // Check against per diem rate for this trip's destination
            $travelRequest = $claim->travelRequest;
            if ($travelRequest !== null) {
                $perDiem = $this->calculatePerDiem(
                    $claim->organization_id,
                    $travelRequest->destination_country,
                    $travelRequest->destination_city,
                    1
                );

                $limit = $perDiem['daily_total'];
                if ($limit > 0) {
                    return [$limit, $amountBase <= $limit];
                }
            }
        }

        return [null, true];
    }

    private function recalculateClaim(TravelExpenseClaim $claim): void
    {
        $total = (float) $claim->lines()->sum('amount_in_base_currency');
        $claim->update(['total_claimed' => round($total, 4)]);
    }
}
