<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'product_price_history';

    protected $fillable = [
        'product_id', 'variant_id', 'price_type', 'old_price', 'new_price',
        'change_percent', 'currency_code', 'reason', 'effective_from',
        'effective_to', 'changed_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:4',
        'new_price' => 'decimal:4',
        'change_percent' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
