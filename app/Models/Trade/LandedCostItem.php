<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LandedCostItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'voucher_id',
        'product_id',
        'variant_id',
        'quantity',
        'purchase_value',
        'weight_kg',
        'volume_cbm',
        'allocated_customs_duty',
        'allocated_freight',
        'allocated_insurance',
        'allocated_clearing',
        'allocated_other',
        'total_additional_cost',
        'total_landed_cost',
        'landed_cost_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'purchase_value' => 'decimal:4',
            'weight_kg' => 'decimal:4',
            'volume_cbm' => 'decimal:4',
            'allocated_customs_duty' => 'decimal:4',
            'allocated_freight' => 'decimal:4',
            'allocated_insurance' => 'decimal:4',
            'allocated_clearing' => 'decimal:4',
            'allocated_other' => 'decimal:4',
            'total_additional_cost' => 'decimal:4',
            'total_landed_cost' => 'decimal:4',
            'landed_cost_per_unit' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'voucher_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function recalculateTotals(): void
    {
        $this->total_additional_cost = (float) $this->allocated_customs_duty
            + (float) $this->allocated_freight
            + (float) $this->allocated_insurance
            + (float) $this->allocated_clearing
            + (float) $this->allocated_other;

        $this->total_landed_cost = (float) $this->purchase_value + (float) $this->total_additional_cost;
        $this->landed_cost_per_unit = (float) $this->quantity > 0
            ? round((float) $this->total_landed_cost / (float) $this->quantity, 4)
            : 0;
    }
}
