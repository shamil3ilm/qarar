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

class ProductionVersion extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'product_id',
        'version_code',
        'description',
        'bom_id',
        'routing_id',
        'lot_size_from',
        'lot_size_to',
        'valid_from',
        'valid_to',
        'production_plant',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'lot_size_from' => 'decimal:4',
        'lot_size_to'   => 'decimal:4',
        'valid_from'    => 'date',
        'valid_to'      => 'date',
        'is_default'    => 'boolean',
        'is_active'     => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class, 'bom_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(RoutingHeader::class, 'routing_id');
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

    public function scopeDefaultForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId)->where('is_default', true);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Check whether a production quantity falls within this version's lot size range.
     */
    public function isValidForLotSize(float $quantity): bool
    {
        if ($quantity < (float) $this->lot_size_from) {
            return false;
        }

        if ($this->lot_size_to !== null && $quantity > (float) $this->lot_size_to) {
            return false;
        }

        return true;
    }
}
