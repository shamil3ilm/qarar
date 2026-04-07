<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotaArrangement extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'product_id',
        'warehouse_id',
        'valid_from',
        'valid_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotaArrangementItem::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Arrangements that cover the given date.
     */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            });
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Sum of all quota percentages across items.
     */
    public function getTotalPercentage(): float
    {
        return (float) $this->items()->sum('quota_percentage');
    }
}
