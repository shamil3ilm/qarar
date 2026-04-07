<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\InventoryBatch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    public const CONDITION_NEW = 'new';
    public const CONDITION_LIKE_NEW = 'like_new';
    public const CONDITION_USED = 'used';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_DEFECTIVE = 'defective';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_RESTOCKED = 'restocked';
    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'invoice_item_id',
        'variant_id',
        'batch_id',
        'description',
        'quantity_returned',
        'quantity_received',
        'quantity_restocked',
        'quantity_damaged',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
        'condition',
        'condition_notes',
        'item_status',
        'warehouse_location_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_restocked' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
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

    public function isRestockable(): bool
    {
        return in_array($this->condition, [self::CONDITION_NEW, self::CONDITION_LIKE_NEW]);
    }
}
