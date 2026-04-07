<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCostCollector extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'product_id',
        'production_line_id',
        'period',
        'fiscal_year',
        'status',
        'standard_cost_total',
        'actual_cost_total',
        'total_variance',
        'quantity_produced',
        'cost_per_unit_standard',
        'cost_per_unit_actual',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period'                 => 'integer',
            'fiscal_year'            => 'integer',
            'standard_cost_total'    => 'decimal:4',
            'actual_cost_total'      => 'decimal:4',
            'total_variance'         => 'decimal:4',
            'quantity_produced'      => 'decimal:4',
            'cost_per_unit_standard' => 'decimal:4',
            'cost_per_unit_actual'   => 'decimal:4',
            'closed_at'              => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductCostCollectorItem::class, 'product_cost_collector_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    // ----------------------------------------------------------------
    // Business helpers
    // ----------------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
