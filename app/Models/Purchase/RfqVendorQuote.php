<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqVendorQuote extends Model
{
    use HasFactory;

    protected $table = 'rfq_vendor_quotes';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price'  => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function rfqVendor(): BelongsTo
    {
        return $this->belongsTo(RfqVendor::class, 'rfq_vendor_id');
    }

    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(RfqLine::class, 'rfq_line_id');
    }
}
