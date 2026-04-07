<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use BelongsToOrganization;
    use HasAuditTrail;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISPOSED = 'disposed';
    public const STATUS_WRITTEN_OFF = 'written_off';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DISPOSED,
        self::STATUS_WRITTEN_OFF,
        self::STATUS_UNDER_MAINTENANCE,
    ];

    // Depreciation method constants
    public const DEPRECIATION_STRAIGHT_LINE = 'straight_line';
    public const DEPRECIATION_DECLINING_BALANCE = 'declining_balance';
    public const DEPRECIATION_UNITS_OF_PRODUCTION = 'units_of_production';
    public const DEPRECIATION_SUM_OF_YEARS_DIGITS = 'sum_of_years_digits';

    public const DEPRECIATION_METHODS = [
        self::DEPRECIATION_STRAIGHT_LINE,
        self::DEPRECIATION_DECLINING_BALANCE,
        self::DEPRECIATION_UNITS_OF_PRODUCTION,
        self::DEPRECIATION_SUM_OF_YEARS_DIGITS,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:4',
            'salvage_value' => 'decimal:4',
            'useful_life_years' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:4',
            'book_value' => 'decimal:4',
            'last_depreciation_date' => 'date',
            'disposal_date' => 'date',
            'disposal_amount' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function depreciationRunLines(): HasMany
    {
        return $this->hasMany(DepreciationRunLine::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssetComponent::class);
    }

    // -------------------------------------------------------------------------
    // Business Logic
    // -------------------------------------------------------------------------

    /**
     * Calculate periodic depreciation amount for the given number of months.
     *
     * Straight-line: (cost - salvage) / useful_life_years / 12 * months
     * Declining balance (200% DB): book_value * (2 / useful_life_years / 12) * months
     * Sum-of-years-digits: pro-rata per month based on SYD fraction
     * Units-of-production: not time-based; falls back to straight-line.
     */
    public function calculatePeriodicDepreciation(int $months = 1): float
    {
        if ($this->isFullyDepreciated()) {
            return 0.0;
        }

        $cost = (float) $this->acquisition_cost;
        $salvage = (float) $this->salvage_value;
        $lifeYears = (float) $this->useful_life_years;
        $bookValue = (float) $this->book_value;

        if ($lifeYears <= 0) {
            return 0.0;
        }

        $depreciation = match ($this->depreciation_method) {
            self::DEPRECIATION_DECLINING_BALANCE => $bookValue * (2.0 / $lifeYears / 12.0) * $months,
            self::DEPRECIATION_SUM_OF_YEARS_DIGITS => $this->calculateSydDepreciation($cost, $salvage, $lifeYears, $months),
            default => ($cost - $salvage) / $lifeYears / 12.0 * $months,
        };

        // Never depreciate below salvage value
        $maxDepreciation = $bookValue - $salvage;

        return (float) max(0.0, min(round($depreciation, 4), $maxDepreciation));
    }

    /**
     * Sum-of-years-digits depreciation for the given number of months.
     */
    private function calculateSydDepreciation(float $cost, float $salvage, float $lifeYears, int $months): float
    {
        $totalLifeMonths = $lifeYears * 12;
        $sydSum = ($lifeYears * ($lifeYears + 1)) / 2;

        // Determine remaining life in years from last depreciation date
        $elapsedMonths = 0;
        if ($this->last_depreciation_date !== null) {
            $elapsedMonths = (int) $this->last_depreciation_date->diffInMonths($this->acquisition_date);
        }

        $remainingMonths = $totalLifeMonths - $elapsedMonths;
        if ($remainingMonths <= 0) {
            return 0.0;
        }

        $annualFraction = $remainingMonths / $totalLifeMonths / $sydSum;

        return ($cost - $salvage) * $annualFraction * $months;
    }

    /**
     * Return true when the asset has been fully depreciated (book_value <= salvage_value).
     */
    public function isFullyDepreciated(): bool
    {
        return (float) $this->book_value <= (float) $this->salvage_value;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Active assets that still have remaining book value above salvage.
     */
    public function scopeDepreciable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereColumn('book_value', '>', 'salvage_value');
    }
}
