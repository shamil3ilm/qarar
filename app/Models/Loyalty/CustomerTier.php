<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTier extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'loyalty_program_id', 'name', 'code', 'color', 'icon',
        'qualification_type', 'min_spending', 'min_points', 'qualification_period_months',
        'earn_rate_multiplier', 'discount_percent', 'free_shipping', 'priority_support_level',
        'perks', 'auto_upgrade', 'auto_downgrade', 'grace_period_days', 'tier_level',
        'is_default', 'is_active',
    ];

    protected $casts = [
        'min_spending' => 'decimal:2',
        'earn_rate_multiplier' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'perks' => 'array',
        'free_shipping' => 'boolean',
        'auto_upgrade' => 'boolean',
        'auto_downgrade' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }
}
