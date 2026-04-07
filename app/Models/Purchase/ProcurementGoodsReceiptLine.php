<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Goods Receipt line item backed by the `procurement_gr_lines` table.
 */
class ProcurementGoodsReceiptLine extends Model
{
    use HasFactory;

    protected $table = 'procurement_gr_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'ordered_qty'  => 'decimal:3',
        'received_qty' => 'decimal:3',
        'accepted_qty' => 'decimal:3',
        'rejected_qty' => 'decimal:3',
        'expiry_date'  => 'date',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(ProcurementGoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
