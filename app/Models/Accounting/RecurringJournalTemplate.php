<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringJournalTemplate extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_ANNUALLY = 'annually';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'run_count',
        'max_runs',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'currency_code',
        'narration',
        'cost_center_id',
        'profit_center_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'last_run_date' => 'date',
            'amount' => 'decimal:4',
            'interval' => 'integer',
            'run_count' => 'integer',
            'max_runs' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: active templates whose next_run_date is today or in the past.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('next_run_date', '<=', now()->toDateString());
    }

    /**
     * Calculate the next run date after the given base date.
     */
    public function calculateNextRunDate(Carbon $from): Carbon
    {
        $next = $from->copy();
        $interval = max(1, (int) $this->interval);

        return match ($this->frequency) {
            self::FREQUENCY_DAILY => $next->addDays($interval),
            self::FREQUENCY_WEEKLY => $next->addWeeks($interval),
            self::FREQUENCY_MONTHLY => $next->addMonthsNoOverflow($interval),
            self::FREQUENCY_QUARTERLY => $next->addMonthsNoOverflow($interval * 3),
            self::FREQUENCY_ANNUALLY => $next->addYears($interval),
            default => $next->addMonthsNoOverflow($interval),
        };
    }
}
