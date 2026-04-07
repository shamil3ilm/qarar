<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class EcommerceOrderItem extends Model
{
    use HasFactory;
    protected $table = 'ecommerce_order_items';

    protected $fillable = [
        'order_id',
        'external_product_id',
        'external_variant_id',
        'sku',
        'name',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'product_id',
        'fulfilled_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'fulfilled_quantity' => 'integer',
        ];
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Business logic
    public function isFullyFulfilled(): bool
    {
        return $this->fulfilled_quantity >= $this->quantity;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity - $this->fulfilled_quantity);
    }
}
