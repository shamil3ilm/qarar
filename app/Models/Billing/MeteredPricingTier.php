<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeteredPricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id', 'metric_type', 'from_quantity', 'to_quantity',
        'price_per_unit', 'unit_label',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:6',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
