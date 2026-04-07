<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostingVersion extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_FROZEN   = 'frozen';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_STANDARD = 'standard';
    public const TYPE_ACTUAL   = 'actual';
    public const TYPE_PLANNED  = 'planned';

    protected $guarded = ['id'];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    // Relationships

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function standardCosts(): HasMany
    {
        return $this->hasMany(ProductStandardCost::class, 'costing_version_id');
    }

    public function costingRuns(): HasMany
    {
        return $this->hasMany(CostingRun::class, 'costing_version_id');
    }

    public function costVariances(): HasMany
    {
        return $this->hasMany(CostVariance::class, 'costing_version_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeFrozen($query)
    {
        return $query->where('status', self::STATUS_FROZEN);
    }

    // Helpers

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

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function freeze(): void
    {
        $this->update(['status' => self::STATUS_FROZEN]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function getDisplayName(): string
    {
        return "{$this->version_code} - {$this->description}";
    }
}
