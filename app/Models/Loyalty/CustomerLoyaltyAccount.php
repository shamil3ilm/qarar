<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLoyaltyAccount extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'contact_id', 'loyalty_program_id', 'customer_tier_id',
        'membership_number', 'total_earned_points', 'total_redeemed_points',
        'total_expired_points', 'available_points', 'pending_points', 'total_spending',
        'spending_this_period', 'enrolled_at', 'tier_qualified_at', 'tier_expires_at',
        'last_activity_at', 'is_active',
    ];

    protected $casts = [
        'total_spending' => 'decimal:2',
        'spending_this_period' => 'decimal:2',
        'enrolled_at' => 'date',
        'tier_qualified_at' => 'date',
        'tier_expires_at' => 'date',
        'last_activity_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class);
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'customer_tier_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class, 'loyalty_account_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'loyalty_account_id');
    }
}
