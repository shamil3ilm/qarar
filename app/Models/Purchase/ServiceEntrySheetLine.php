<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEntrySheetLine extends Model
{
    protected $fillable = [
        'service_entry_sheet_id',
        'service_po_line_id',
        'actual_quantity',
        'uom',
        'actual_price',
        'total_amount',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'actual_quantity' => 'decimal:4',
            'actual_price' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }

    public function entrySheet(): BelongsTo
    {
        return $this->belongsTo(ServiceEntrySheet::class);
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(ServicePoLine::class, 'service_po_line_id');
    }
}
