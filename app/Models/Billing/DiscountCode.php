<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'code', 'name', 'description', 'discount_type', 'discount_value',
        'max_discount_amount', 'min_order_amount', 'applies_to',
        'applicable_plan_ids', 'max_uses', 'max_uses_per_org', 'times_used',
        'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'applicable_plan_ids' => 'array',
        'starts_at' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountCodeUsage::class, 'discount_code_id');
    }
}
