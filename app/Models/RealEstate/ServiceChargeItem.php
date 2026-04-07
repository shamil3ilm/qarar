<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceChargeItem extends Model
{
    protected $table = 're_service_charge_items';

    protected $fillable = [
        'settlement_id',
        'cost_category',
        'actual_cost',
        'lettable_area_sqm',
        'cost_per_sqm',
        'allocation_basis',
        'description',
    ];

    protected $casts = [
        'actual_cost' => 'decimal:4',
        'lettable_area_sqm' => 'decimal:4',
        'cost_per_sqm' => 'decimal:6',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ServiceChargeSettlement::class, 'settlement_id');
    }
}
