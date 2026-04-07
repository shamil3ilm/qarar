<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Goods Receipt model backed by the `procurement_goods_receipts` table.
 *
 * A separate model is used because `goods_receipts` (backed by an existing
 * migration) already belongs to a different class with different columns.
 */
class ProcurementGoodsReceipt extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_CONFIRMED    = 'confirmed';
    public const STATUS_QUALITY_HOLD = 'quality_hold';

    protected $table = 'procurement_goods_receipts';

    protected $guarded = ['id'];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementGoodsReceiptLine::class, 'goods_receipt_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
