<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStandardCost extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'material_cost'       => 'decimal:4',
        'labor_cost'          => 'decimal:4',
        'overhead_cost'       => 'decimal:4',
        'subcontracting_cost' => 'decimal:4',
        'total_standard_cost' => 'decimal:4',
        'cost_per_unit'       => 'decimal:4',
        'calculated_at'       => 'datetime',
    ];

    // Relationships

    public function costingVersion(): BelongsTo
    {
        return $this->belongsTo(CostingVersion::class, 'costing_version_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class, 'bom_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(CostComponent::class, 'standard_cost_id');
    }

    // Helpers

    public function getTotalCost(): float
    {
        return (float) bcadd(
            bcadd(
                bcadd((string) $this->material_cost, (string) $this->labor_cost, 4),
                (string) $this->overhead_cost,
                4
            ),
            (string) $this->subcontracting_cost,
            4
        );
    }
}
