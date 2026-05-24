<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionLot extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_INSPECTION = 'in_inspection';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PARTIAL_ACCEPT = 'partial_accept';

    // Source type constants
    public const SOURCE_PURCHASE_ORDER = 'purchase_order';
    public const SOURCE_PRODUCTION = 'production';
    public const SOURCE_TRANSFER = 'transfer';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'lot_number',
        'quality_plan_id',
        'product_id',
        'warehouse_id',
        'source_type',
        'source_id',
        'quantity',
        'inspected_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'status',
        'inspection_date',
        'inspected_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'inspected_quantity' => 'decimal:4',
        'accepted_quantity' => 'decimal:4',
        'rejected_quantity' => 'decimal:4',
        'inspection_date' => 'date',
    ];

    // Relationships

    public function qualityPlan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class)->orderBy('created_at');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usageDecision(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UsageDecision::class, 'inspection_lot_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInInspection($query)
    {
        return $query->where('status', self::STATUS_IN_INSPECTION);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_PARTIAL_ACCEPT,
        ]);
    }

    // Helper Methods

    /**
     * Calculate the acceptance rate as a percentage of inspected quantity.
     */
    public function getAcceptanceRate(): float
    {
        $inspected = (float) $this->inspected_quantity;

        if ($inspected === 0.0) {
            return 0.0;
        }

        return round(((float) $this->accepted_quantity / $inspected) * 100, 2);
    }

    /**
     * Determine whether all items in the lot have been inspected.
     */
    public function isComplete(): bool
    {
        $inspected = (float) $this->inspected_quantity;
        $total = (float) $this->quantity;

        return $total > 0 && $inspected >= $total;
    }

    /**
     * Finalise the inspection by recording accepted/rejected quantities and
     * transitioning the status accordingly.
     *
     * Returns a fresh instance with the updated attributes.
     */
    public function complete(float $accepted, float $rejected, int $userId): self
    {
        $inspected = $accepted + $rejected;
        $total = (float) $this->quantity;

        $status = match (true) {
            $accepted >= $total => self::STATUS_ACCEPTED,
            $rejected >= $total => self::STATUS_REJECTED,
            default => self::STATUS_PARTIAL_ACCEPT,
        };

        $this->update([
            'accepted_quantity' => $accepted,
            'rejected_quantity' => $rejected,
            'inspected_quantity' => $inspected,
            'status' => $status,
            'inspection_date' => now()->toDateString(),
            'inspected_by' => $userId,
        ]);

        return $this->fresh();
    }

    /**
     * Mark the lot as being actively inspected.
     */
    public function startInspection(): self
    {
        $this->update(['status' => self::STATUS_IN_INSPECTION]);

        return $this->fresh();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInInspection(): bool
    {
        return $this->status === self::STATUS_IN_INSPECTION;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
