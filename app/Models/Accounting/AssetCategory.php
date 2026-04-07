<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    // Depreciation methods
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
            'default_salvage_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    public function glAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_asset_account_id');
    }

    public function glDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_depreciation_account_id');
    }

    public function glAccumulatedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_accumulated_account_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
