<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class MrpPlannedOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_PRODUCTION = 'production';
    public const TYPE_TRANSFER = 'transfer';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_FIRMED = 'firmed';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'mrp_run_id',
        'product_id',
        'order_type',
        'planned_quantity',
        'planned_start_date',
        'planned_end_date',
        'status',
        'source_demand_id',
        'notes',
        'converted_at',
        'converted_to_type',
        'converted_to_id',
        'purchase_requisition_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity'  => 'decimal:4',
            'planned_start_date' => 'date',
            'planned_end_date'   => 'date',
            'converted_at'       => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class, 'mrp_run_id');
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    /**
     * Firm the planned order — sets status to firmed.
     */
    public function firm(int $userId): self
    {
        $this->update(['status' => self::STATUS_FIRMED]);

        return $this->fresh();
    }

    /**
     * Convert planned order to a purchase order.
     */
    public function convertToPurchaseOrder(int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($userId) {
            $purchaseOrder = PurchaseOrder::create([
                'organization_id'   => $this->organization_id,
                'supplier_id'       => null, // To be assigned by user after conversion
                'order_date'        => now()->toDateString(),
                'expected_delivery' => $this->planned_end_date->toDateString(),
                'status'            => 'draft',
                'notes'             => "Auto-generated from MRP planned order #{$this->uuid}",
                'created_by'        => $userId,
            ]);

            $this->update([
                'status'            => self::STATUS_CONVERTED,
                'converted_at'      => now(),
                'converted_to_type' => PurchaseOrder::class,
                'converted_to_id'   => $purchaseOrder->id,
            ]);

            return $purchaseOrder;
        });
    }

    /**
     * Convert planned order to a work order.
     */
    public function convertToWorkOrder(int $userId): WorkOrder
    {
        return DB::transaction(function () use ($userId) {
            $workOrder = WorkOrder::create([
                'organization_id'    => $this->organization_id,
                'product_id'         => $this->product_id,
                'planned_quantity'   => $this->planned_quantity,
                'produced_quantity'  => 0,
                'rejected_quantity'  => 0,
                'planned_start_date' => $this->planned_start_date->toDateString(),
                'planned_end_date'   => $this->planned_end_date->toDateString(),
                'status'             => WorkOrder::STATUS_DRAFT,
                'priority'           => WorkOrder::PRIORITY_NORMAL,
                'notes'              => "Auto-generated from MRP planned order #{$this->uuid}",
                'created_by'         => $userId,
            ]);

            $this->update([
                'status'            => self::STATUS_CONVERTED,
                'converted_at'      => now(),
                'converted_to_type' => WorkOrder::class,
                'converted_to_id'   => $workOrder->id,
            ]);

            return $workOrder;
        });
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isFirmed(): bool
    {
        return $this->status === self::STATUS_FIRMED;
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    public function canBeFirmed(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function canBeConverted(): bool
    {
        return in_array($this->status, [self::STATUS_PLANNED, self::STATUS_FIRMED], true);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeFirmed($query)
    {
        return $query->where('status', self::STATUS_FIRMED);
    }
}
