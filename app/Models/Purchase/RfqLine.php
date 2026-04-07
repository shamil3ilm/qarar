<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqLine extends Model
{
    use HasFactory;

    protected $table = 'rfq_lines';

    protected $guarded = ['id'];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function vendorQuotes(): HasMany
    {
        return $this->hasMany(RfqVendorQuote::class, 'rfq_line_id');
    }
}
