<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SAP PP: Planned Independent Requirement (PIR / MD61).
 *
 * A time-phased production quantity planned by the planning team that drives
 * Make-to-Stock MRP without requiring a confirmed sales order.
 */
class PlannedIndependentRequirement extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'planned_independent_requirements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
            'requirement_date'  => 'date',
            'valid_from'        => 'date',
            'valid_to'          => 'date',
            'is_active'         => 'boolean',
            'version'           => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Only active PIRs for the active planning version. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    /** Active PIRs whose requirement_date falls within the given planning horizon. */
    public function scopeWithinHorizon($query, string $from, string $to)
    {
        return $query->active()
            ->whereBetween('requirement_date', [$from, $to]);
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    /**
     * Net open quantity after consumption by sales orders.
     */
    public function openQuantity(): float
    {
        $net = bcsub((string) $this->quantity, (string) $this->consumed_quantity, 4);

        return max(0.0, (float) $net);
    }

    /**
     * Consume the given quantity from this PIR (reduce open qty).
     */
    public function consume(float $qty): void
    {
        $newConsumed = bcadd((string) $this->consumed_quantity, (string) $qty, 4);
        $this->update(['consumed_quantity' => $newConsumed]);
    }
}
