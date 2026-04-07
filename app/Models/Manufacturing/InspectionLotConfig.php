<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionLotConfig extends Model
{
    use BelongsToOrganization, HasFactory;

    public const TRIGGER_GOODS_RECEIPT         = 'goods_receipt';
    public const TRIGGER_GOODS_ISSUE           = 'goods_issue';
    public const TRIGGER_PRODUCTION_COMPLETION = 'production_completion';
    public const TRIGGER_MANUAL                = 'manual';

    protected $fillable = [
        'organization_id',
        'product_id',
        'inspection_trigger',
        'auto_create',
        'sample_percentage',
        'quality_plan_id',
    ];

    protected $casts = [
        'auto_create'        => 'boolean',
        'sample_percentage'  => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function qualityPlan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('inspection_trigger', $trigger);
    }

    public function scopeAutoCreate($query)
    {
        return $query->where('auto_create', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Calculate the sample quantity based on the configured sample percentage.
     */
    public function getSampleQuantity(float $totalQuantity): float
    {
        return round($totalQuantity * ((float) $this->sample_percentage / 100), 4);
    }
}
