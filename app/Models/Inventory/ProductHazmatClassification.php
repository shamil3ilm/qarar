<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductHazmatClassification extends Model
{
    protected $table = 'product_hazmat_classifications';

    protected $fillable = [
        'product_id',
        'hazmat_classification_id',
        'storage_class_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hazmatClassification(): BelongsTo
    {
        return $this->belongsTo(HazmatClassification::class);
    }

    public function storageClass(): BelongsTo
    {
        return $this->belongsTo(HazmatStorageClass::class, 'storage_class_id');
    }
}
