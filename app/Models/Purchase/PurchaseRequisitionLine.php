<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'requisition_id',
        'product_id',
        'variant_id',
        'quantity',
        'uom_id',
        'estimated_unit_price',
        'preferred_vendor_id',
        'warehouse_id',
        'required_by_date',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'estimated_unit_price' => 'decimal:4',
            'required_by_date' => 'date',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'preferred_vendor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function getEstimatedTotal(): float
    {
        if ($this->estimated_unit_price === null) {
            return 0.0;
        }

        return (float) bcmul((string) $this->quantity, (string) $this->estimated_unit_price, 4);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeConvertible($query)
    {
        return $query->whereIn('status', ['open', 'partially_converted']);
    }
}
