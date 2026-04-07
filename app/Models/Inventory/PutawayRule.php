<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PutawayRule extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'priority'  => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    public function preferredLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'preferred_location_id');
    }

    /**
     * Determine whether this rule applies to the given product and category.
     * A rule matches when its product_id or product_category_id aligns with the
     * supplied values. A rule without either constraint is a catch-all.
     */
    public function matches(int $productId, int $categoryId): bool
    {
        if ($this->product_id !== null && $this->product_id !== $productId) {
            return false;
        }

        if ($this->product_category_id !== null && $this->product_category_id !== $categoryId) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
