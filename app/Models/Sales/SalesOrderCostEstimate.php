<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\CostingVersion;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderCostEstimate extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_RELEASED = 'released';
    public const STATUS_OBSOLETE = 'obsolete';

    protected $fillable = [
        'organization_id',
        'sales_order_id',
        'quotation_id',
        'costing_version_id',
        'status',
        'total_cost',
        'total_revenue',
        'gross_margin',
        'gross_margin_percent',
        'costed_by',
        'costed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cost'           => 'decimal:4',
            'total_revenue'        => 'decimal:4',
            'gross_margin'         => 'decimal:4',
            'gross_margin_percent' => 'decimal:4',
            'costed_at'            => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function costingVersion(): BelongsTo
    {
        return $this->belongsTo(CostingVersion::class, 'costing_version_id');
    }

    public function costedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'costed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderCostEstimateItem::class, 'sales_order_cost_estimate_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // ----------------------------------------------------------------
    // Business helpers
    // ----------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }
}
