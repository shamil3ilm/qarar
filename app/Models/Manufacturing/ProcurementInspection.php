<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementInspection extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_REJECTED    = 'rejected';

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'goods_receipt_id',
        'product_id',
        'vendor_id',
        'inspection_lot_id',
        'quantity_received',
        'quantity_to_inspect',
        'quantity_inspected',
        'quantity_accepted',
        'quantity_rejected',
        'status',
        'defect_rate',
        'inspection_date',
        'inspected_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received'   => 'decimal:4',
            'quantity_to_inspect' => 'decimal:4',
            'quantity_inspected'  => 'decimal:4',
            'quantity_accepted'   => 'decimal:4',
            'quantity_rejected'   => 'decimal:4',
            'defect_rate'         => 'decimal:2',
            'inspection_date'     => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function inspectionLot(): BelongsTo
    {
        return $this->belongsTo(InspectionLot::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProcurementInspectionResult::class);
    }

    public function calculateDefectRate(): float
    {
        $inspected = (float) $this->quantity_inspected;

        if ($inspected === 0.0) {
            return 0.0;
        }

        return round(((float) $this->quantity_rejected / $inspected) * 100, 2);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }
}
