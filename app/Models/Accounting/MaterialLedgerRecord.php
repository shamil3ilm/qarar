<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialLedgerRecord extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period'                    => 'integer',
            'fiscal_year'               => 'integer',
            'opening_stock_qty'         => 'decimal:4',
            'opening_stock_value'       => 'decimal:4',
            'closing_stock_qty'         => 'decimal:4',
            'closing_stock_value'       => 'decimal:4',
            'cumulative_receipts_qty'   => 'decimal:4',
            'cumulative_receipts_value' => 'decimal:4',
            'cumulative_issues_qty'     => 'decimal:4',
            'cumulative_issues_value'   => 'decimal:4',
            'standard_price'            => 'decimal:4',
            'actual_price'              => 'decimal:4',
            'price_difference'          => 'decimal:4',
            'price_unit'                => 'integer',
            'closed_at'                 => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function mlDocuments(): HasMany
    {
        return $this->hasMany(MlDocument::class);
    }

    public function closingEntries(): HasMany
    {
        return $this->hasMany(MlClosingEntry::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForPeriod(Builder $query, int $period, int $fiscalYear): Builder
    {
        return $query->where('period', $period)->where('fiscal_year', $fiscalYear);
    }

    /**
     * Calculate the actual cost per unit based on cumulative data.
     * Returns standard_price if no receipts have been posted yet.
     */
    public function getActualCost(): float
    {
        $totalQty   = (float) $this->opening_stock_qty + (float) $this->cumulative_receipts_qty;
        $totalValue = (float) $this->opening_stock_value + (float) $this->cumulative_receipts_value;

        if ($totalQty <= 0 || $this->price_unit <= 0) {
            return (float) $this->standard_price;
        }

        return round(($totalValue / $totalQty) * $this->price_unit, 4);
    }

    /**
     * Calculate current stock value using the actual price.
     */
    public function getStockValue(): float
    {
        $qty         = (float) $this->closing_stock_qty;
        $actualPrice = (float) $this->actual_price;
        $priceUnit   = max(1, (int) $this->price_unit);

        return round($qty * ($actualPrice / $priceUnit), 4);
    }
}
