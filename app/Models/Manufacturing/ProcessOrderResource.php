<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessOrderResource extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'process_order_id',
        'recipe_resource_id',
        'material_id',
        'planned_quantity',
        'actual_quantity',
        'unit_id',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity'  => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function processOrder(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class);
    }

    public function recipeResource(): BelongsTo
    {
        return $this->belongsTo(RecipeResource::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
