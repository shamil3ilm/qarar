<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancePaymentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'advance_payment_id',
        'invoice_id',
        'applied_amount',
        'applied_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_date'   => 'date',
            'applied_amount' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function advancePayment(): BelongsTo
    {
        return $this->belongsTo(AdvancePayment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
