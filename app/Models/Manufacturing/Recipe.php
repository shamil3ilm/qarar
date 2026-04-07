<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_MASTER  = 'master';
    public const TYPE_CONTROL = 'control';

    protected $fillable = [
        'organization_id',
        'product_id',
        'recipe_code',
        'name',
        'base_quantity',
        'base_unit_id',
        'recipe_type',
        'validity_from',
        'validity_to',
        'is_active',
    ];

    protected $casts = [
        'base_quantity' => 'decimal:4',
        'validity_from' => 'date',
        'validity_to'   => 'date',
        'is_active'     => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(RecipePhase::class)->orderBy('phase_number');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(RecipeResource::class);
    }

    public function processOrders(): HasMany
    {
        return $this->hasMany(ProcessOrder::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
