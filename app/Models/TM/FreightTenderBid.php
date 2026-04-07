<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightTenderBid extends Model
{
    use HasUuid;

    protected $table = 'tm_freight_tender_bids';

    protected $fillable = [
        'tender_request_id',
        'carrier_id',
        'total_price',
        'currency_code',
        'transit_days',
        'valid_until',
        'status',
        'submitted_at',
        'evaluated_at',
        'notes',
        'breakdown',
    ];

    protected $casts = [
        'total_price' => 'decimal:4',
        'transit_days' => 'integer',
        'valid_until' => 'date',
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'breakdown' => 'array',
    ];

    public function tenderRequest(): BelongsTo
    {
        return $this->belongsTo(FreightTenderRequest::class, 'tender_request_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }
}
