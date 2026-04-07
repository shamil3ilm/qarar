<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeasonalCampaign extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'code', 'description', 'campaign_type',
        'banner_image', 'theme_color', 'starts_at', 'ends_at', 'is_recurring',
        'recurrence_rule', 'discount_type', 'discount_value', 'max_discount',
        'min_purchase', 'applies_to', 'applicable_category_ids', 'applicable_product_ids',
        'applicable_bundle_ids', 'excluded_product_ids', 'max_uses',
        'max_uses_per_customer', 'times_used', 'budget_limit', 'budget_used',
        'promotional_message', 'send_notification', 'show_countdown', 'is_active', 'priority',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'budget_limit' => 'decimal:2',
        'budget_used' => 'decimal:2',
        'applicable_category_ids' => 'array',
        'applicable_product_ids' => 'array',
        'applicable_bundle_ids' => 'array',
        'excluded_product_ids' => 'array',
        'is_recurring' => 'boolean',
        'send_notification' => 'boolean',
        'show_countdown' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tierOffers(): HasMany
    {
        return $this->hasMany(CampaignTierOffer::class, 'campaign_id');
    }
}
