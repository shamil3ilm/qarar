<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'description', 'currency_name', 'currency_symbol',
        'point_value', 'earn_rate', 'min_redeem_points', 'points_expiry_days',
        'allow_partial_redeem', 'earn_on_tax', 'earn_on_shipping', 'is_active',
    ];

    protected $casts = [
        'point_value' => 'decimal:4',
        'earn_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'allow_partial_redeem' => 'boolean',
        'earn_on_tax' => 'boolean',
        'earn_on_shipping' => 'boolean',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(CustomerTier::class, 'loyalty_program_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyAccount::class, 'loyalty_program_id');
    }

    public function earningRules(): HasMany
    {
        return $this->hasMany(PointsEarningRule::class, 'loyalty_program_id');
    }

    public function rewardsCatalog(): HasMany
    {
        return $this->hasMany(RewardsCatalogItem::class, 'loyalty_program_id');
    }
}
