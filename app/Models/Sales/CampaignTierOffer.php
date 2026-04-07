<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTierOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'tier_code', 'tier_name', 'min_purchase_amount',
        'discount_type', 'discount_value', 'max_discount',
        'extra_discount_percent', 'bonus_points',
        'early_access', 'early_access_hours', 'description', 'is_active',
    ];

    protected $casts = [
        'extra_discount_percent' => 'decimal:2',
        'early_access' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SeasonalCampaign::class, 'campaign_id');
    }
}
