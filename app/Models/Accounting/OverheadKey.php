<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OverheadKey extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_QUANTITY   = 'quantity';

    public const TYPES = [
        self::TYPE_PERCENTAGE,
        self::TYPE_QUANTITY,
    ];

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'overhead_type',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function rates(): HasMany
    {
        return $this->hasMany(OverheadKeyRate::class, 'overhead_key_id')->orderBy('validity_from', 'desc');
    }

    public function costingSheetRows(): HasMany
    {
        return $this->hasMany(CostingSheetRow::class, 'overhead_key_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isPercentage(): bool
    {
        return $this->overhead_type === self::TYPE_PERCENTAGE;
    }
}
