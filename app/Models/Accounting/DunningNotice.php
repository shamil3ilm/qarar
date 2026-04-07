<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DunningNotice extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_BLOCKED  = 'blocked';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_overdue' => 'decimal:4',
            'notice_date'   => 'date',
            'sent_at'       => 'datetime',
        ];
    }

    public function dunningRun(): BelongsTo
    {
        return $this->belongsTo(DunningRun::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function dunningLevel(): BelongsTo
    {
        return $this->belongsTo(DunningLevel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DunningNoticeItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
