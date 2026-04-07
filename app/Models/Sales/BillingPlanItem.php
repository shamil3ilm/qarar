<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BillingPlanItem extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_BILLED = 'billed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'billing_plan_id',
        'milestone_description',
        'billing_date',
        'billing_percent',
        'billing_amount',
        'status',
        'invoice_id',
        'billed_at',
        'sort_order',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'billing_percent' => 'decimal:2',
        'billing_amount' => 'decimal:4',
        'billed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isDue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && Carbon::parse($this->billing_date)->lte(Carbon::today());
    }
}
