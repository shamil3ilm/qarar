<?php

declare(strict_types=1);

namespace App\Models\TM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightRateLine extends Model
{
    protected $table = 'tm_freight_rate_lines';

    protected $fillable = [
        'rate_table_id',
        'origin_zone',
        'destination_zone',
        'weight_from',
        'weight_to',
        'volume_from',
        'volume_to',
        'base_rate',
        'per_unit_rate',
        'min_charge',
        'max_charge',
    ];

    protected $casts = [
        'weight_from' => 'decimal:3',
        'weight_to' => 'decimal:3',
        'volume_from' => 'decimal:4',
        'volume_to' => 'decimal:4',
        'base_rate' => 'decimal:4',
        'per_unit_rate' => 'decimal:6',
        'min_charge' => 'decimal:4',
        'max_charge' => 'decimal:4',
    ];

    public function rateTable(): BelongsTo
    {
        return $this->belongsTo(FreightRateTable::class, 'rate_table_id');
    }
}
