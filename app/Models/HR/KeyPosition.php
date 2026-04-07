<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KeyPosition extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'key_positions';

    protected $guarded = ['id'];

    public const CRITICALITY_CRITICAL = 'critical';
    public const CRITICALITY_HIGH = 'high';
    public const CRITICALITY_MEDIUM = 'medium';

    protected function casts(): array
    {
        return [
            'target_fill_date' => 'date',
            'min_successors' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_holder_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(SuccessionCandidate::class, 'key_position_id');
    }

    public function activeCandidates(): HasMany
    {
        return $this->hasMany(SuccessionCandidate::class, 'key_position_id')->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCriticality($query, string $criticality)
    {
        return $query->where('criticality', $criticality);
    }

    public function isReadyNowCovered(): bool
    {
        return $this->candidates()
            ->where('readiness', SuccessionCandidate::READINESS_READY_NOW)
            ->where('is_active', true)
            ->exists();
    }
}
