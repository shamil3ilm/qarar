<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceChargeAllocation extends Model
{
    protected $table = 're_service_charge_allocations';

    protected $fillable = [
        'settlement_id',
        'contract_id',
        'unit_area_sqm',
        'allocation_pct',
        'actual_amount',
        'billed_amount',
        'adjustment_amount',
    ];

    protected $casts = [
        'unit_area_sqm' => 'decimal:4',
        'allocation_pct' => 'decimal:4',
        'actual_amount' => 'decimal:4',
        'billed_amount' => 'decimal:4',
        'adjustment_amount' => 'decimal:4',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ServiceChargeSettlement::class, 'settlement_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }
}
