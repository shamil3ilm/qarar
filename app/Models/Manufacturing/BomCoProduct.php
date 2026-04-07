<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomCoProduct extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_CO_PRODUCT = 'co_product';
    public const TYPE_BY_PRODUCT = 'by_product';
    public const TYPE_SCRAP = 'scrap';

    protected $fillable = [
        'organization_id',
        'bom_template_id',
        'product_id',
        'co_product_type',
        'quantity_per_base',
        'unit_of_measure',
        'cost_allocation_percent',
        'is_valuated',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'quantity_per_base' => 'decimal:4',
        'cost_allocation_percent' => 'decimal:2',
        'is_valuated' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    // Relationships

    public function bomTemplate(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes

    public function scopeForBom(Builder $query, int $bomId): Builder
    {
        return $query->where('bom_template_id', $bomId);
    }

    public function scopeCoProducts(Builder $query): Builder
    {
        return $query->where('co_product_type', self::TYPE_CO_PRODUCT);
    }

    public function scopeByProducts(Builder $query): Builder
    {
        return $query->where('co_product_type', self::TYPE_BY_PRODUCT);
    }

    // Helpers

    public function isCoProduct(): bool
    {
        return $this->co_product_type === self::TYPE_CO_PRODUCT;
    }

    public function isByProduct(): bool
    {
        return $this->co_product_type === self::TYPE_BY_PRODUCT;
    }
}
