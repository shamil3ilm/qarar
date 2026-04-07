<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessionCandidate extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'succession_candidates';

    protected $guarded = ['id'];

    public const READINESS_READY_NOW = 'ready_now';
    public const READINESS_ONE_TWO_YEARS = 'one_two_years';
    public const READINESS_THREE_FIVE_YEARS = 'three_five_years';

    protected function casts(): array
    {
        return [
            'nomination_date' => 'date',
            'last_reviewed_at' => 'date',
            'performance_rating' => 'integer',
            'potential_rating' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function keyPosition(): BelongsTo
    {
        return $this->belongsTo(KeyPosition::class, 'key_position_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function nominatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SuccessionPoolActivity::class, 'candidate_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByReadiness($query, string $readiness)
    {
        return $query->where('readiness', $readiness);
    }

    public function isReadyNow(): bool
    {
        return $this->readiness === self::READINESS_READY_NOW;
    }
}
