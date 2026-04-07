<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class BillingPlan extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_MILESTONE = 'milestone';
    public const TYPE_PERIODIC = 'periodic';

    protected $fillable = [
        'organization_id',
        'sales_order_id',
        'quotation_id',
        'plan_type',
        'billing_currency',
        'total_value',
        'billed_value',
        'status',
        'start_date',
        'end_date',
        'periodic_interval_days',
        'notes',
    ];

    protected $casts = [
        'total_value' => 'decimal:4',
        'billed_value' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
        'periodic_interval_days' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingPlanItem::class)->orderBy('sort_order')->orderBy('billing_date');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForSalesOrder(Builder $query, int $salesOrderId): Builder
    {
        return $query->where('sales_order_id', $salesOrderId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getDueItems(): Collection
    {
        return $this->items()
            ->where('status', BillingPlanItem::STATUS_PENDING)
            ->where('billing_date', '<=', Carbon::today())
            ->get();
    }

    public function getNextBillingDate(): ?Carbon
    {
        $nextItem = $this->items()
            ->where('status', BillingPlanItem::STATUS_PENDING)
            ->orderBy('billing_date')
            ->first();

        return $nextItem ? Carbon::parse($nextItem->billing_date) : null;
    }
}
