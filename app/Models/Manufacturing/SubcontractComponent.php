<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractComponent extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'required_quantity'    => 'decimal:4',
        'transferred_quantity' => 'decimal:4',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubcontractOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transferLines(): HasMany
    {
        return $this->hasMany(SubcontractTransferLine::class, 'component_line_id');
    }

    // Helpers

    public function getRemainingQuantity(): float
    {
        return max(0.0, (float) $this->required_quantity - (float) $this->transferred_quantity);
    }

    public function isFullyTransferred(): bool
    {
        return (float) $this->transferred_quantity >= (float) $this->required_quantity;
    }
}
