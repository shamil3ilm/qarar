<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProbationPeriod extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXTENDED = 'extended';
    public const STATUS_FAILED = 'failed';
    public const STATUS_WAIVED = 'waived';

    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_EXTENDED = 'extended';
    public const OUTCOME_TERMINATED = 'terminated';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'start_date',
        'end_date',
        'extended_end_date',
        'status',
        'review_date',
        'outcome',
        'outcome_date',
        'reviewer_id',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'date',
            'end_date'          => 'date',
            'extended_end_date' => 'date',
            'review_date'       => 'date',
            'outcome_date'      => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDueSoon(Builder $query, int $daysAhead = 30): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('end_date', '<=', now()->addDays($daysAhead))
            ->whereDate('end_date', '>=', now()->toDateString());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('end_date', '<', now()->toDateString());
    }

    // Helpers

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->end_date->lt(now()->toDateString());
    }

    public function getDaysRemaining(): int
    {
        $effectiveEnd = $this->extended_end_date ?? $this->end_date;

        $remaining = (int) now()->diffInDays($effectiveEnd, false);

        return max(0, $remaining);
    }

    public function extend(string $newEndDate): void
    {
        $this->extended_end_date = $newEndDate;
        $this->status            = self::STATUS_EXTENDED;
        $this->save();
    }

    public function complete(string $outcome, int $reviewerId, ?string $notes): void
    {
        $this->status       = self::STATUS_COMPLETED;
        $this->outcome      = $outcome;
        $this->outcome_date = now()->toDateString();
        $this->reviewer_id  = $reviewerId;
        $this->review_notes = $notes;
        $this->save();
    }
}
