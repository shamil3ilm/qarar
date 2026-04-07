<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoAssessmentCycle extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_ASSESSMENT  = 'assessment';
    public const TYPE_DISTRIBUTION = 'distribution';

    public const STATUS_OPEN     = 'open';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'cycle_type',
        'fiscal_year',
        'period_from',
        'period_to',
        'status',
        'executed_at',
        'executed_by',
        'copa_enabled',
        'copa_segment_id',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'   => 'integer',
            'period_from'   => 'integer',
            'period_to'     => 'integer',
            'executed_at'   => 'datetime',
            'copa_enabled'  => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function segments(): HasMany
    {
        return $this->hasMany(CoAssessmentCycleSegment::class, 'assessment_cycle_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(CoAssessmentPosting::class, 'assessment_cycle_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function copaSegment(): BelongsTo
    {
        return $this->belongsTo(ProfitabilitySegment::class, 'copa_segment_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isExecuted(): bool
    {
        return $this->status === self::STATUS_EXECUTED;
    }
}
