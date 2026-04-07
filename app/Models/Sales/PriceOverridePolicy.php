<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOverridePolicy extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'description', 'allow_price_change', 'allow_discount',
        'allow_markup', 'allow_free_item', 'max_discount_percent', 'max_markup_percent',
        'max_discount_amount', 'min_price_percent', 'max_total_discount_percent',
        'requires_approval', 'approval_threshold_percent', 'approval_threshold_amount',
        'requires_reason', 'applies_to', 'applicable_role_ids', 'applicable_user_ids',
        'applicable_branch_ids', 'is_default', 'is_active',
    ];

    protected $casts = [
        'allow_price_change' => 'boolean',
        'allow_discount' => 'boolean',
        'allow_markup' => 'boolean',
        'allow_free_item' => 'boolean',
        'requires_approval' => 'boolean',
        'requires_reason' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'max_discount_percent' => 'decimal:2',
        'max_markup_percent' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'applicable_role_ids' => 'array',
        'applicable_user_ids' => 'array',
        'applicable_branch_ids' => 'array',
    ];
}
