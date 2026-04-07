<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingHeader extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'product_id',
        'routing_number',
        'alternative',
        'is_default',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'routing_id')
            ->orderBy('sequence_number');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeValid($query, ?string $date = null)
    {
        $date ??= now()->toDateString();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
        });
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Calculate total lead time in hours for a given production quantity.
     * Lead time = sum of (setup_time + machine_time * qty + labor_time * qty) per operation.
     */
    public function calculateLeadTime(float $quantity): float
    {
        $total = 0.0;

        foreach ($this->operations as $operation) {
            $setup   = (float) $operation->setup_time;
            $machine = (float) $operation->machine_time * $quantity;
            $labor   = (float) $operation->labor_time * $quantity;
            $total   += $setup + $machine + $labor;
        }

        return round($total, 4);
    }
}
