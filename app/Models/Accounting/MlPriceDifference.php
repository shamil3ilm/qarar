<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlPriceDifference extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    public const CATEGORY_PURCHASE_PRICE_VARIANCE  = 'purchase_price_variance';
    public const CATEGORY_EXCHANGE_RATE_DIFFERENCE = 'exchange_rate_difference';
    public const CATEGORY_INVOICE_DIFFERENCE       = 'invoice_difference';
    public const CATEGORY_PRODUCTION_VARIANCE      = 'production_variance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount'            => 'decimal:4',
            'quantity_affected' => 'decimal:4',
        ];
    }

    public function closingEntry(): BelongsTo
    {
        return $this->belongsTo(MlClosingEntry::class, 'ml_closing_entry_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
