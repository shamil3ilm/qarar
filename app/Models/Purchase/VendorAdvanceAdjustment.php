<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdvanceAdjustment extends Model
{
    use HasFactory;

    protected $table = 'vendor_advance_adjustments';

    protected $guarded = ['id'];

    protected $casts = [
        'adjusted_amount' => 'decimal:2',
        'adjusted_at'     => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function vendorAdvance(): BelongsTo
    {
        return $this->belongsTo(VendorAdvance::class, 'vendor_advance_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
