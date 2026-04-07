<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferPriceVersion extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_FROZEN,
    ];

    protected $fillable = [
        'organization_id',
        'version_name',
        'fiscal_year',
        'status',
        'created_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(TransferPriceCondition::class, 'version_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }
}
