<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreightAgreement extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'tm_freight_agreements';

    protected $fillable = [
        'organization_id',
        'carrier_id',
        'agreement_number',
        'valid_from',
        'valid_to',
        'currency_code',
        'status',
        'rate_table_id',
        'annual_volume_commitment',
        'annual_spend_commitment',
        'payment_term_days',
        'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'annual_volume_commitment' => 'decimal:4',
        'annual_spend_commitment' => 'decimal:4',
        'payment_term_days' => 'integer',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function rateTable(): BelongsTo
    {
        return $this->belongsTo(FreightRateTable::class, 'rate_table_id');
    }
}
