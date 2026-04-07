<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTagAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'tag_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(ProductTag::class, 'tag_id');
    }
}
