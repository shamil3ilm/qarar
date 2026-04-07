<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaseContract extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use HasAuditTrail;
    use SoftDeletes;

    public const ROLE_LESSEE  = 'lessee';
    public const ROLE_LESSOR  = 'lessor';

    public const CLASS_FINANCE    = 'finance';
    public const CLASS_OPERATING  = 'operating';
    public const CLASS_SHORT_TERM = 'short_term';
    public const CLASS_LOW_VALUE  = 'low_value';

    public const STATUS_ACTIVE     = 'active';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_MODIFIED   = 'modified';

    public const FREQ_MONTHLY     = 'monthly';
    public const FREQ_QUARTERLY   = 'quarterly';
    public const FREQ_SEMI_ANNUAL = 'semi_annual';
    public const FREQ_ANNUAL      = 'annual';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'commencement_date'    => 'date',
            'end_date'             => 'date',
            'termination_date'     => 'date',
            'lease_term_months'    => 'integer',
            'payment_amount'       => 'decimal:4',
            'discount_rate'        => 'decimal:6',
            'initial_rou_asset'    => 'decimal:4',
            'initial_lease_liability'  => 'decimal:4',
            'current_lease_liability'  => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function schedule(): HasMany
    {
        return $this->hasMany(LeaseSchedule::class)->orderBy('period_number');
    }

    public function rouAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'rou_asset_account_id');
    }

    public function accumDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accum_depreciation_account_id');
    }

    public function leaseLiabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'lease_liability_account_id');
    }

    public function interestExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'interest_expense_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Business logic helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFinanceLease(): bool
    {
        return $this->classification === self::CLASS_FINANCE;
    }

    /** Number of payments per year based on frequency. */
    public function paymentsPerYear(): int
    {
        return match ($this->payment_frequency) {
            self::FREQ_MONTHLY     => 12,
            self::FREQ_QUARTERLY   => 4,
            self::FREQ_SEMI_ANNUAL => 2,
            self::FREQ_ANNUAL      => 1,
        };
    }

    /** Periodic discount rate (annual rate ÷ periods per year). */
    public function periodicRate(): float
    {
        return (float) $this->discount_rate / $this->paymentsPerYear();
    }

    /** Total number of payment periods. */
    public function totalPeriods(): int
    {
        return (int) round($this->lease_term_months / (12 / $this->paymentsPerYear()));
    }

    public function nextUnpostedPeriod(): ?LeaseSchedule
    {
        return $this->schedule()->where('is_posted', false)->orderBy('period_number')->first();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeFinance($query)
    {
        return $query->where('classification', self::CLASS_FINANCE);
    }
}
