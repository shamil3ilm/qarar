<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GosiContribution extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'gosi_contributions';

    protected $guarded = ['id'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'basic_salary' => 'decimal:2',
            'total_salary' => 'decimal:2',
            'contributable_salary' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'hazard_contribution' => 'decimal:2',
            'total_contribution' => 'decimal:2',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Resolve the active GOSI configuration for this contribution's org and country.
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(GosiConfiguration::class, 'organization_id', 'organization_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }
}
