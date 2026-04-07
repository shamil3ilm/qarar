<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardsCatalogItem extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $table = 'rewards_catalog';

    protected $fillable = [
        'organization_id', 'loyalty_program_id', 'name', 'description', 'image_path',
        'reward_type', 'type', 'value', 'points_cost', 'points_required', 'monetary_value',
        'discount_percent', 'discount_amount', 'min_order_amount', 'product_id',
        'stock_quantity', 'redeemed_quantity', 'max_per_customer', 'required_tier_code',
        'available_from', 'available_until', 'is_featured', 'is_active', 'display_order',
    ];

    protected $casts = [
        'monetary_value' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'available_from' => 'date',
        'available_until' => 'date',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }
}
