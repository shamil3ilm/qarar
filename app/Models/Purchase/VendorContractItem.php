<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorContractItem extends Model
{
    use HasFactory;

    protected $table = 'vendor_contract_items';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'quantity'   => 'decimal:3',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function vendorContract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class, 'vendor_contract_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
