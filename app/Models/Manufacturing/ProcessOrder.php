<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_CREATED     = 'created';
    public const STATUS_RELEASED    = 'released';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'organization_id',
        'recipe_id',
        'product_id',
        'order_number',
        'planned_quantity',
        'actual_quantity',
        'unit_id',
        'batch_number',
        'planned_start',
        'planned_finish',
        'actual_start',
        'actual_finish',
        'status',
        'production_version_id',
        'created_by',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity'  => 'decimal:4',
        'planned_start'    => 'datetime',
        'planned_finish'   => 'datetime',
        'actual_start'     => 'datetime',
        'actual_finish'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function productionVersion(): BelongsTo
    {
        return $this->belongsTo(ProductionVersion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProcessOrderPhase::class)->orderBy('phase_number');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ProcessOrderResource::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RELEASED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeReleased(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function canBeCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_RELEASED, self::STATUS_IN_PROGRESS], true);
    }
}
