<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\ProductBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PurchaseReturnItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'bill_item_id',
        'variant_id',
        'batch_id',
        'description',
        'quantity_returned',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'condition',
        'condition_notes',
        'item_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
