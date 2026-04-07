<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description', 'tier', 'billing_cycle', 'base_price',
        'currency_code', 'max_users', 'max_branches', 'storage_limit_mb',
        'max_invoices_per_month', 'max_products', 'max_customers', 'max_employees',
        'api_calls_per_month', 'included_modules', 'features', 'trial_days',
        'trial_requires_card', 'is_public', 'is_popular', 'display_order', 'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'included_modules' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_popular' => 'boolean',
        'trial_requires_card' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class, 'plan_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class, 'plan_id');
    }

    public function meteredPricingTiers(): HasMany
    {
        return $this->hasMany(MeteredPricingTier::class, 'plan_id');
    }
}
