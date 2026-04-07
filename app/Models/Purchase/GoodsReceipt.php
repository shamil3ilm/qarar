<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_INSPECTION = 'in_inspection';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gr_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'gr_id');
    }

    /**
     * The inspection lot created for this GR when quality inspection is required.
     * The FK lives on goods_receipts.inspection_lot_id so we use a BelongsTo here.
     */
    public function inspectionLot(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InspectionLot::class, 'inspection_lot_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInInspection(): bool
    {
        return $this->status === self::STATUS_IN_INSPECTION;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * A GR can be posted from draft (no QI required) or from in_inspection
     * (after the inspection lot has been resolved).
     */
    public function canBePosted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_INSPECTION], true);
    }

    public function canBeReversed(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function getTotalCost(): float
    {
        return (float) $this->lines()->sum('total_cost');
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForPurchaseOrder($query, int $poId)
    {
        return $query->where('purchase_order_id', $poId);
    }
}
