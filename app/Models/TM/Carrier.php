<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrier extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_carriers';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'type',
        'status',
        'scac_code',
        'iata_code',
        'country_code',
        'currency_code',
        'payment_term_days',
        'rating',
        'notes',
    ];

    protected $casts = [
        'payment_term_days' => 'integer',
        'rating' => 'decimal:2',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(CarrierService::class, 'carrier_id');
    }

    public function performance(): HasMany
    {
        return $this->hasMany(CarrierPerformance::class, 'carrier_id');
    }

    public function rateAgreements(): HasMany
    {
        return $this->hasMany(FreightAgreement::class, 'carrier_id');
    }
}
