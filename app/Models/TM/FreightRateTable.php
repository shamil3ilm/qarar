<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreightRateTable extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_freight_rate_tables';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'carrier_id',
        'carrier_service_id',
        'valid_from',
        'valid_to',
        'currency_code',
        'basis',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function carrierService(): BelongsTo
    {
        return $this->belongsTo(CarrierService::class, 'carrier_service_id');
    }

    public function rateLines(): HasMany
    {
        return $this->hasMany(FreightRateLine::class, 'rate_table_id');
    }

    public function surcharges(): HasMany
    {
        return $this->hasMany(FreightSurcharge::class, 'rate_table_id');
    }
}
