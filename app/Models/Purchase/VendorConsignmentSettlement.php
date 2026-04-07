<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorConsignmentSettlement extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PAID      = 'paid';

    protected $table = 'vendor_consignment_settlements';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'settlement_period_from',
        'settlement_period_to',
        'total_quantity',
        'total_value',
        'currency_code',
        'status',
        'bill_id',
        'settled_at',
        'settled_by',
    ];

    protected function casts(): array
    {
        return [
            'settlement_period_from' => 'date',
            'settlement_period_to'   => 'date',
            'total_quantity'         => 'decimal:4',
            'total_value'            => 'decimal:4',
            'settled_at'             => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
