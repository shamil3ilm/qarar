<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceChargeSettlement extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 're_service_charge_settlements';

    protected $fillable = [
        'organization_id',
        'settlement_number',
        'property_id',
        'settlement_year',
        'status',
        'total_actual_costs',
        'total_billed_to_tenants',
        'total_adjustment',
        'currency_code',
        'settlement_date',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'settlement_year' => 'integer',
        'total_actual_costs' => 'decimal:4',
        'total_billed_to_tenants' => 'decimal:4',
        'total_adjustment' => 'decimal:4',
        'settlement_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(ServiceChargeItem::class, 'settlement_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ServiceChargeAllocation::class, 'settlement_id');
    }
}
