<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetComponent extends Model
{
    use BelongsToOrganization;
    use HasAuditTrail;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquisition_date'       => 'date',
            'acquisition_cost'       => 'decimal:4',
            'salvage_value'          => 'decimal:4',
            'useful_life_years'      => 'decimal:2',
            'accumulated_depreciation' => 'decimal:4',
            'book_value'             => 'decimal:4',
            'retirement_date'        => 'date',
            'retirement_amount'      => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    public function isFullyDepreciated(): bool
    {
        return (float) $this->book_value <= (float) $this->salvage_value;
    }

    /**
     * Calculate monthly straight-line depreciation for the component.
     * Components inherit parent asset method when their own method is null.
     */
    public function calculateMonthlyDepreciation(): float
    {
        if ($this->isFullyDepreciated() || (float) $this->useful_life_years <= 0) {
            return 0.0;
        }

        $depreciable = (float) $this->acquisition_cost - (float) $this->salvage_value;

        return round($depreciable / ((float) $this->useful_life_years * 12), 4);
    }
}
