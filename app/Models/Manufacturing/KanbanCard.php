<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KanbanCard extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_FULL             = 'full';
    public const STATUS_EMPTY            = 'empty';
    public const STATUS_IN_REPLENISHMENT = 'in_replenishment';
    public const STATUS_WAITING          = 'waiting';

    protected $fillable = [
        'control_cycle_id',
        'card_number',
        'status',
        'current_quantity',
        'emptied_at',
        'replenishment_triggered_at',
        'filled_at',
        'triggered_document_id',
        'triggered_document_type',
    ];

    protected $casts = [
        'current_quantity'            => 'decimal:4',
        'emptied_at'                  => 'datetime',
        'replenishment_triggered_at'  => 'datetime',
        'filled_at'                   => 'datetime',
        'triggered_document_id'       => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function controlCycle(): BelongsTo
    {
        return $this->belongsTo(KanbanControlCycle::class, 'control_cycle_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeFull($query)
    {
        return $query->where('status', self::STATUS_FULL);
    }

    public function scopeEmpty($query)
    {
        return $query->where('status', self::STATUS_EMPTY);
    }

    public function scopeInReplenishment($query)
    {
        return $query->where('status', self::STATUS_IN_REPLENISHMENT);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isFull(): bool
    {
        return $this->status === self::STATUS_FULL;
    }

    public function isEmpty(): bool
    {
        return $this->status === self::STATUS_EMPTY;
    }

    public function isInReplenishment(): bool
    {
        return $this->status === self::STATUS_IN_REPLENISHMENT;
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function canSignalEmpty(): bool
    {
        return $this->status === self::STATUS_FULL;
    }

    public function canSignalFull(): bool
    {
        return in_array($this->status, [self::STATUS_IN_REPLENISHMENT, self::STATUS_WAITING, self::STATUS_EMPTY], true);
    }
}
