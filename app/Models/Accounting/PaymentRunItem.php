<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRunItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'open_amount'    => 'decimal:4',
            'payment_amount' => 'decimal:4',
            'discount_taken' => 'decimal:4',
            'due_date'       => 'date',
        ];
    }

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_INCLUDED = 'included';
    public const STATUS_EXCLUDED = 'excluded';
    public const STATUS_PAID     = 'paid';

    public const DOC_TYPE_BILL           = 'bill';
    public const DOC_TYPE_PURCHASE_ORDER = 'purchase_order';
    public const DOC_TYPE_INVOICE        = 'invoice';

    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    public function scopeIncluded($query)
    {
        return $query->where('status', self::STATUS_INCLUDED);
    }

    public function scopeProposed($query)
    {
        return $query->where('status', self::STATUS_PROPOSED);
    }
}
