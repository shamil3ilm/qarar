<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionVariance extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_MATERIAL  = 'material';
    public const TYPE_LABOUR    = 'labour';
    public const TYPE_OVERHEAD  = 'overhead';
    public const TYPE_YIELD     = 'yield';

    protected $guarded = ['id'];

    protected $casts = [
        'standard_cost'   => 'decimal:4',
        'actual_cost'     => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'variance_pct'    => 'decimal:2',
        'period_date'     => 'date',
        'posted_to_gl'    => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costVersion(): BelongsTo
    {
        return $this->belongsTo(CostVersion::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeUnposted($query)
    {
        return $query->where('posted_to_gl', false);
    }

    public function scopeForPeriod($query, string $date)
    {
        return $query->where('period_date', $date);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('variance_type', $type);
    }
}
