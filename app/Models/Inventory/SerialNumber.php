<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SerialNumber extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_IN_STOCK   = 'in_stock';
    public const STATUS_SOLD       = 'sold';
    public const STATUS_RETURNED   = 'returned';
    public const STATUS_SCRAPPED   = 'scrapped';
    public const STATUS_IN_TRANSIT = 'in_transit';

    protected $fillable = [
        'organization_id',
        'serial_number',
        'product_id',
        'product_variant_id',
        'batch_id',
        'warehouse_id',
        'location_id',
        'status',
        'manufacture_date',
        'expiry_date',
        'warranty_expiry_date',
        'received_at',
        'sold_at',
        'sold_to_contact_id',
        'current_document_type',
        'current_document_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date'      => 'date',
            'expiry_date'           => 'date',
            'warranty_expiry_date'  => 'date',
            'received_at'           => 'datetime',
            'sold_at'               => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function soldTo(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'sold_to_contact_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SerialNumberMovement::class)->latest('moved_at');
    }

    public function isInStock(): bool
    {
        return $this->status === self::STATUS_IN_STOCK;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isScrapped(): bool
    {
        return $this->status === self::STATUS_SCRAPPED;
    }

    public function scopeInStock($query)
    {
        return $query->where('status', self::STATUS_IN_STOCK);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
