<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Three-Way Match result backed by the `three_way_match_results` table
 * created in the 2026_03_25_000004 migration.
 *
 * Note: an earlier model (ThreeWayMatchResult) may reference a different
 * table from a different migration. This model targets the table defined
 * by the procurement migration.
 */
class ProcurementThreeWayMatchResult extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_MATCHED               = 'matched';
    public const STATUS_PO_QTY_VARIANCE       = 'po_qty_variance';
    public const STATUS_PRICE_VARIANCE        = 'price_variance';
    public const STATUS_GR_MISSING            = 'gr_missing';
    public const STATUS_PASSED_WITH_TOLERANCE = 'passed_with_tolerance';

    protected $table = 'procurement_match_results';

    protected $guarded = ['id'];

    protected $casts = [
        'po_total'        => 'decimal:2',
        'gr_total'        => 'decimal:2',
        'bill_total'      => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'variance_pct'    => 'decimal:2',
        'tolerance_pct'   => 'decimal:2',
        'matched_at'      => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(ProcurementGoodsReceipt::class, 'goods_receipt_id');
    }

    // ----------------------------------------------------------------
    // Business Logic
    // ----------------------------------------------------------------

    public function isPassed(): bool
    {
        return in_array($this->match_status, [
            self::STATUS_MATCHED,
            self::STATUS_PASSED_WITH_TOLERANCE,
        ], true);
    }
}
