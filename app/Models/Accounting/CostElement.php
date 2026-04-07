<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostElement extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_PRIMARY   = 'primary';
    public const TYPE_SECONDARY = 'secondary';

    public const CATEGORY_GENERAL             = 'general';
    public const CATEGORY_DEPRECIATION        = 'depreciation';
    public const CATEGORY_IMPUTED             = 'imputed';
    public const CATEGORY_REVENUE             = 'revenue';
    public const CATEGORY_INTERNAL_SETTLEMENT = 'internal_settlement';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'element_type',
        'gl_account_id',
        'cost_element_category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class, 'cost_element_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isPrimary(): bool
    {
        return $this->element_type === self::TYPE_PRIMARY;
    }

    public function isSecondary(): bool
    {
        return $this->element_type === self::TYPE_SECONDARY;
    }
}
