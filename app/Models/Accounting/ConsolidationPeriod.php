<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidationPeriod extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    protected $fillable = [
        'organization_id',
        'consolidation_group_id',
        'fiscal_year_id',
        'period_start',
        'period_end',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end'   => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eliminationEntries(): HasMany
    {
        return $this->hasMany(EliminationEntry::class);
    }

    public function consolidatedBalances(): HasMany
    {
        return $this->hasMany(ConsolidatedBalance::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }
}
