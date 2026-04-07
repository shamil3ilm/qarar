<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAddon extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name', 'code', 'description', 'addon_type', 'price', 'pricing_model',
        'billing_cycle', 'unit_quantity', 'unit_label', 'compatible_plans', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compatible_plans' => 'array',
        'is_active' => 'boolean',
    ];
}
