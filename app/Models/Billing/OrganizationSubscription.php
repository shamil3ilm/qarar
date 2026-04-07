<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationSubscription extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'plan_id', 'status', 'starts_at', 'ends_at',
        'trial_ends_at', 'cancelled_at', 'cancellation_reason', 'base_price',
        'discount_amount', 'discount_percent', 'discount_code', 'max_users',
        'max_branches', 'storage_limit_mb', 'max_invoices_per_month',
        'enabled_modules', 'enabled_features', 'auto_renew', 'payment_method_id',
        'next_billing_date',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'trial_ends_at' => 'date',
        'cancelled_at' => 'date',
        'next_billing_date' => 'date',
        'base_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'enabled_modules' => 'array',
        'enabled_features' => 'array',
        'auto_renew' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function addonPurchases(): HasMany
    {
        return $this->hasMany(SubscriptionAddonPurchase::class, 'subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'subscription_id');
    }
}
