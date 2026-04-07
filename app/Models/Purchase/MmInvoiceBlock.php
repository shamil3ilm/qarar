<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MmInvoiceBlock extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CLEARED = 'cleared';

    public const TYPE_MANUAL = 'manual';
    public const TYPE_TOLERANCE = 'tolerance';
    public const TYPE_PRICE = 'price';
    public const TYPE_QUANTITY = 'quantity';
    public const TYPE_DATE = 'date';
    public const TYPE_STOCHASTIC = 'stochastic';

    protected $fillable = [
        'organization_id',
        'bill_id',
        'block_type',
        'block_reason',
        'blocked_by',
        'blocked_at',
        'released_by',
        'released_at',
        'release_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
