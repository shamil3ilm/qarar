<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepetitiveMfgScheduleLine extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PLANNED   = 'planned';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIAL   = 'partial';

    protected $fillable = [
        'repetitive_mfg_schedule_id',
        'schedule_date',
        'planned_quantity',
        'confirmed_quantity',
        'status',
    ];

    protected $casts = [
        'schedule_date'      => 'date',
        'planned_quantity'   => 'decimal:4',
        'confirmed_quantity' => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RepetitiveMfgSchedule::class, 'repetitive_mfg_schedule_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }
}
