<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class ReturnsInspectionLot extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_INSPECTION = 'in_inspection';
    public const STATUS_USAGE_DECISION_MADE = 'usage_decision_made';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // Return type constants
    public const TYPE_CUSTOMER = 'customer_return';
    public const TYPE_VENDOR = 'vendor_return';
    public const TYPE_INTERNAL = 'internal_return';

    // Usage decision constants
    public const DECISION_ACCEPT = 'accept';
    public const DECISION_REJECT = 'reject';
    public const DECISION_REWORK = 'rework';
    public const DECISION_PARTIAL_ACCEPT = 'partial_accept';

    protected $fillable = [
        'organization_id',
        'rma_request_id',
        'sales_return_id',
        'purchase_return_id',
        'product_id',
        'warehouse_id',
        'lot_number',
        'return_type',
        'status',
        'received_quantity',
        'inspected_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'rework_quantity',
        'usage_decision',
        'usage_decision_by',
        'usage_decision_at',
        'usage_decision_notes',
        'inspection_start_date',
        'inspection_end_date',
        'quality_plan_id',
        'stock_posted',
        'stock_posted_at',
        'created_by',
    ];

    protected $casts = [
        'received_quantity'    => 'decimal:4',
        'inspected_quantity'   => 'decimal:4',
        'accepted_quantity'    => 'decimal:4',
        'rejected_quantity'    => 'decimal:4',
        'rework_quantity'      => 'decimal:4',
        'stock_posted'         => 'boolean',
        'usage_decision_at'    => 'datetime',
        'stock_posted_at'      => 'datetime',
        'inspection_start_date' => 'date',
        'inspection_end_date'  => 'date',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function rmaRequest(): BelongsTo
    {
        if (class_exists(\App\Models\Sales\RmaRequest::class)) {
            return $this->belongsTo(\App\Models\Sales\RmaRequest::class);
        }

        // Fallback: return an unresolvable relation rather than crashing
        return $this->belongsTo(self::class, 'rma_request_id');
    }

    public function salesReturn(): BelongsTo
    {
        if (class_exists(\App\Models\Sales\SalesReturn::class)) {
            return $this->belongsTo(\App\Models\Sales\SalesReturn::class);
        }

        return $this->belongsTo(self::class, 'sales_return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function qualityPlan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(ReturnsInspectionDefect::class);
    }

    public function usageDecisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usage_decision_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInInspection($query)
    {
        return $query->where('status', self::STATUS_IN_INSPECTION);
    }

    public function scopePendingUsageDecision($query)
    {
        return $query->where('status', self::STATUS_IN_INSPECTION);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    public function canStartInspection(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function canMakeUsageDecision(): bool
    {
        return $this->status === self::STATUS_IN_INSPECTION;
    }

    public function canPostStock(): bool
    {
        return $this->status === self::STATUS_USAGE_DECISION_MADE
            && ! $this->stock_posted;
    }

    public function getUnaccountedQuantity(): float
    {
        return (float) $this->received_quantity
            - (float) $this->accepted_quantity
            - (float) $this->rejected_quantity
            - (float) $this->rework_quantity;
    }

    public function getTotalDefectCount(): int
    {
        return $this->defects()->count();
    }

    public function hasAnyDefects(): bool
    {
        return $this->getTotalDefectCount() > 0;
    }

    public function startInspection(): void
    {
        $this->status = self::STATUS_IN_INSPECTION;
        $this->inspection_start_date = now()->toDateString();
        $this->save();
    }

    public function makeUsageDecision(
        string $decision,
        float $accepted,
        float $rejected,
        float $rework,
        int $userId,
        ?string $notes
    ): void {
        $total = (float) $this->received_quantity;
        $sum = $accepted + $rejected + $rework;

        if ($sum > $total + 0.0001) {
            throw new InvalidArgumentException(
                "The sum of accepted ({$accepted}), rejected ({$rejected}), and rework ({$rework}) "
                . "quantities ({$sum}) exceeds the received quantity ({$total})."
            );
        }

        $this->usage_decision        = $decision;
        $this->accepted_quantity     = $accepted;
        $this->rejected_quantity     = $rejected;
        $this->rework_quantity       = $rework;
        $this->inspected_quantity    = $sum;
        $this->usage_decision_by     = $userId;
        $this->usage_decision_at     = now();
        $this->usage_decision_notes  = $notes;
        $this->status                = self::STATUS_USAGE_DECISION_MADE;
        $this->inspection_end_date   = now()->toDateString();
        $this->save();
    }
}
