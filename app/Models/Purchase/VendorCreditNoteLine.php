<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCreditNoteLine extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'vendor_credit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(VendorCreditNote::class, 'vendor_credit_note_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
