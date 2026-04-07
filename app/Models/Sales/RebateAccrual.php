<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebateAccrual extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'rebate_master_id',
        'invoice_id',
        'accrual_date',
        'invoice_amount',
        'rebate_amount',
        'journal_entry_id',
        'status',
        'settlement_ref',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date'   => 'date',
            'invoice_amount' => 'decimal:4',
            'rebate_amount'  => 'decimal:4',
            'settled_at'     => 'datetime',
        ];
    }

    public function rebateMaster(): BelongsTo
    {
        return $this->belongsTo(RebateMaster::class, 'rebate_master_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForRebate($query, int $rebateMasterId)
    {
        return $query->where('rebate_master_id', $rebateMasterId);
    }
}
