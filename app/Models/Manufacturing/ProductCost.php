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

class ProductCost extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'material_cost'       => 'decimal:4',
        'labour_cost'         => 'decimal:4',
        'overhead_cost'       => 'decimal:4',
        'subcontracting_cost' => 'decimal:4',
        'total_cost'          => 'decimal:4',
        'effective_from'      => 'date',
        'effective_to'        => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costVersion(): BelongsTo
    {
        return $this->belongsTo(CostVersion::class);
    }

    public function costedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'costed_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Return the total cost as a float.
     */
    public function getTotalCost(): float
    {
        return (float) $this->total_cost;
    }

    /**
     * Recalculate and return the sum of all cost components.
     */
    public function computeTotal(): float
    {
        return (float) bcadd(
            bcadd(
                bcadd((string) $this->material_cost, (string) $this->labour_cost, 4),
                (string) $this->overhead_cost,
                4
            ),
            (string) $this->subcontracting_cost,
            4
        );
    }
}
