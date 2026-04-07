<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StabilityStudyTimePoint extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const STATUS_SCHEDULED    = 'scheduled';
    public const STATUS_IN_PROGRESS  = 'in_progress';
    public const STATUS_COMPLETED    = 'completed';
    public const STATUS_MISSED       = 'missed';

    protected $fillable = [
        'organization_id',
        'stability_study_id',
        'time_point',
        'scheduled_date',
        'actual_date',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'actual_date'    => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(StabilityStudy::class, 'stability_study_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(StabilityStudyResult::class, 'stability_study_time_point_id');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
