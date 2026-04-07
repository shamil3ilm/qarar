<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceVolumeBreak extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_qty'      => 'decimal:4',
            'max_qty'      => 'decimal:4',
            'unit_price'   => 'decimal:4',
            'discount_pct' => 'decimal:2',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Return the effective unit price after applying the discount percentage.
     */
    public function getEffectivePrice(): string
    {
        if ($this->discount_pct > 0) {
            $discount = bcmul(
                (string) $this->unit_price,
                bcdiv((string) $this->discount_pct, '100', 6),
                4
            );
            return bcsub((string) $this->unit_price, $discount, 4);
        }

        return (string) $this->unit_price;
    }

    /**
     * Check whether the supplied quantity falls within this break's range.
     */
    public function appliesToQuantity(float $quantity): bool
    {
        if ((float) $this->min_qty > $quantity) {
            return false;
        }

        if ($this->max_qty !== null && (float) $this->max_qty < $quantity) {
            return false;
        }

        return true;
    }
}
