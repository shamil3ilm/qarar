<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeResource extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'recipe_id',
        'recipe_phase_id',
        'material_id',
        'quantity',
        'unit_id',
        'is_co_product',
        'is_by_product',
    ];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'is_co_product' => 'boolean',
        'is_by_product' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipePhase(): BelongsTo
    {
        return $this->belongsTo(RecipePhase::class);
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
