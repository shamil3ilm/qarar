<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAdvice extends Model
{
    use HasUuid, BelongsToOrganization;

    protected $table = 'payment_advices';

    protected $guarded = ['id'];

    protected $casts = [
        'amount'          => 'decimal:4',
        'payment_date'    => 'date',
        'sent_at'         => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SENT         = 'sent';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_CANCELLED    = 'cancelled';

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function houseBank(): BelongsTo
    {
        return $this->belongsTo(HouseBank::class);
    }

    public function houseBankAccount(): BelongsTo
    {
        return $this->belongsTo(HouseBankAccount::class);
    }
}
