<?php

declare(strict_types=1);

namespace App\Models\TM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightTenderItem extends Model
{
    protected $table = 'tm_freight_tender_items';

    protected $fillable = [
        'tender_request_id',
        'description',
        'weight',
        'volume',
        'quantity',
        'unit_of_measure',
        'cargo_type',
        'is_dangerous_goods',
        'un_number',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'volume' => 'decimal:4',
        'quantity' => 'decimal:3',
        'is_dangerous_goods' => 'boolean',
    ];

    public function tenderRequest(): BelongsTo
    {
        return $this->belongsTo(FreightTenderRequest::class, 'tender_request_id');
    }
}
