<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'attribute_id', 'value_text', 'value_number',
        'value_boolean', 'value_json',
    ];

    protected $casts = [
        'value_number' => 'decimal:4',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }
}
