<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightSurcharge extends Model
{
    use HasUuid;

    protected $table = 'tm_freight_surcharges';

    protected $fillable = [
        'organization_id',
        'rate_table_id',
        'carrier_id',
        'code',
        'name',
        'type',
        'calculation_method',
        'value',
        'currency_code',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:6',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function rateTable(): BelongsTo
    {
        return $this->belongsTo(FreightRateTable::class, 'rate_table_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }
}
