<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'name', 'file_path', 'file_type', 'file_size',
        'document_type', 'language', 'is_public', 'display_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
