<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\Sales\Contact;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorConsignmentStock extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'vendor_consignment_stocks';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'unit_id',
        'vendor_price',
        'currency_code',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand'  => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'vendor_price'      => 'decimal:4',
            'last_movement_at'  => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(VendorConsignmentReceipt::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(VendorConsignmentWithdrawal::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(VendorConsignmentSettlement::class, 'vendor_id', 'vendor_id')
            ->where('organization_id', $this->organization_id);
    }

    public function getAvailableQuantity(): float
    {
        return (float) bcsub(
            (string) $this->quantity_on_hand,
            (string) $this->quantity_reserved,
            4
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', '>', 0);
    }

    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
