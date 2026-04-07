<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DpsScreeningRun extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $table = 'dps_screening_runs';

    public const STATUS_CLEAN            = 'clean';
    public const STATUS_POTENTIAL_MATCH  = 'potential_match';
    public const STATUS_CONFIRMED_MATCH  = 'confirmed_match';
    public const STATUS_CLEARED          = 'cleared';

    public const TRIGGER_MANUAL           = 'manual';
    public const TRIGGER_AUTO_TRANSACTION = 'auto_transaction';
    public const TRIGGER_BATCH            = 'batch';

    protected $fillable = [
        'organization_id',
        'screened_entity_type',
        'screened_entity_id',
        'screening_date',
        'match_threshold',
        'status',
        'cleared_by',
        'cleared_at',
        'clearance_notes',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'screening_date'  => 'datetime',
            'match_threshold' => 'decimal:2',
            'cleared_at'      => 'datetime',
        ];
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(DpsScreeningResult::class);
    }

    public function isClean(): bool
    {
        return $this->status === self::STATUS_CLEAN;
    }

    public function requiresReview(): bool
    {
        return in_array($this->status, [self::STATUS_POTENTIAL_MATCH, self::STATUS_CONFIRMED_MATCH], true);
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [self::STATUS_POTENTIAL_MATCH, self::STATUS_CONFIRMED_MATCH]);
    }
}
