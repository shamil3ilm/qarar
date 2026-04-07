<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Models\Loyalty\CustomerLoyaltyAccount;
use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\PointsTransaction;
use App\Models\Loyalty\RewardRedemption;
use App\Models\Loyalty\RewardsCatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyService
{
    public function getPrograms(int $organizationId): mixed
    {
        return LoyaltyProgram::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('tiers')
            ->get();
    }

    public function createProgram(array $data): LoyaltyProgram
    {
        return LoyaltyProgram::create($data);
    }

    public function enrollCustomer(int $contactId, int $programId): CustomerLoyaltyAccount
    {
        $program = LoyaltyProgram::findOrFail($programId);
        $defaultTier = $program->tiers()->where('is_default', true)->first();

        return CustomerLoyaltyAccount::firstOrCreate(
            [
                'contact_id'         => $contactId,
                'loyalty_program_id' => $programId,
                'organization_id'    => $program->organization_id,
            ],
            [
                'customer_tier_id' => $defaultTier?->id,
                'membership_number' => 'LYL-' . strtoupper(Str::random(8)),
                'enrolled_at' => now(),
                'is_active' => true,
            ]
        );
    }

    public function earnPoints(int $accountId, int $points, string $description, ?string $sourceType = null, ?int $sourceId = null, ?float $sourceAmount = null): PointsTransaction
    {
        return DB::transaction(function () use ($accountId, $points, $description, $sourceType, $sourceId, $sourceAmount) {
            $account = CustomerLoyaltyAccount::lockForUpdate()->findOrFail($accountId);

            $transaction = PointsTransaction::create([
                'loyalty_account_id' => $accountId,
                'transaction_type' => 'earn',
                'points' => $points,
                'balance_before' => $account->available_points,
                'balance_after' => $account->available_points + $points,
                'description' => $description,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_amount' => $sourceAmount,
                'earn_multiplier' => (float) ($account->tier?->earn_rate_multiplier ?? 1.0),
            ]);

            $account->increment('available_points', $points);
            $account->increment('total_earned_points', $points);
            $account->update(['last_activity_at' => now()]);

            return $transaction;
        });
    }

    public function redeemReward(int $accountId, int $rewardId): RewardRedemption
    {
        return DB::transaction(function () use ($accountId, $rewardId) {
            $account = CustomerLoyaltyAccount::lockForUpdate()->findOrFail($accountId);
            $reward = RewardsCatalogItem::findOrFail($rewardId);

            if ($account->available_points < $reward->points_cost) {
                throw new \RuntimeException('Insufficient points for redemption.');
            }

            $transaction = PointsTransaction::create([
                'loyalty_account_id' => $accountId,
                'transaction_type' => 'redeem',
                'points' => -$reward->points_cost,
                'balance_before' => $account->available_points,
                'balance_after' => $account->available_points - $reward->points_cost,
                'description' => "Redeemed: {$reward->name}",
            ]);

            $account->decrement('available_points', $reward->points_cost);
            $account->increment('total_redeemed_points', $reward->points_cost);

            $redemption = RewardRedemption::create([
                'loyalty_account_id' => $accountId,
                'reward_id' => $rewardId,
                'points_transaction_id' => $transaction->id,
                'points_spent' => $reward->points_cost,
                'status' => 'pending',
                'redemption_code' => strtoupper(Str::random(10)),
            ]);

            $reward->increment('redeemed_quantity');

            return $redemption;
        });
    }

    public function getAvailableRewards(int $accountId): mixed
    {
        $account = CustomerLoyaltyAccount::findOrFail($accountId);

        return RewardsCatalogItem::where('loyalty_program_id', $account->loyalty_program_id)
            ->where('is_active', true)
            ->where('points_cost', '<=', $account->available_points)
            ->where(function ($q) {
                $q->whereNull('available_from')->orWhere('available_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('available_until')->orWhere('available_until', '>=', now());
            })
            ->orderBy('display_order')
            ->get();
    }
}
