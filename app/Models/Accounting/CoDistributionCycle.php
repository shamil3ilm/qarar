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

class CoDistributionCycle extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_OPEN     = 'open';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'name',
        'fiscal_year',
        'period_from',
        'period_to',
        'status',
        'executed_at',
        'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'period_from' => 'integer',
            'period_to'   => 'integer',
            'executed_at' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function segments(): HasMany
    {
        return $this->hasMany(CoDistributionSegment::class, 'distribution_cycle_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(CoDistributionPosting::class, 'distribution_cycle_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
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
