<?php

declare(strict_types=1);

namespace App\Models\Trade;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ImportExportShipmentItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'shipment_id',
        'product_id',
        'variant_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total_value',
        'weight_kg',
        'tariff_code',
        'country_of_origin',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total_value' => 'decimal:4',
            'weight_kg' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ImportExportShipment::class, 'shipment_id');
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

    public function scopeForTariffCode(Builder $query, string $tariffCode): Builder
    {
        return $query->where('tariff_code', $tariffCode);
    }
}
