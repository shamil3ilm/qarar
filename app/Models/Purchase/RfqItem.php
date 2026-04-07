<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    use HasFactory;

    protected $table = 'rfq_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function quoteLines(): HasMany
    {
        return $this->hasMany(RfqQuoteLine::class, 'rfq_item_id');
    }
}
