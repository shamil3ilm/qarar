<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuccessionPlanCandidate extends Model
{
    protected $table = 'succession_plan_candidates';

    protected $guarded = ['id'];

    public const READINESS_READY_NOW = 'ready_now';
    public const READINESS_READY_1_YEAR = 'ready_1_year';
    public const READINESS_READY_2_YEARS = 'ready_2_years';
    public const READINESS_DEVELOPMENT_NEEDED = 'development_needed';

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
        ];
    }

    public function successionPlan(): BelongsTo
    {
        return $this->belongsTo(SuccessionPlan::class, 'succession_plan_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isReadyNow(): bool
    {
        return $this->readiness === self::READINESS_READY_NOW;
    }

    public function scopeByReadiness($query, string $readiness)
    {
        return $query->where('readiness', $readiness);
    }
}
