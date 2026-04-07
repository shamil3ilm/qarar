<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostComponent extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    // Relationships

    public function standardCost(): BelongsTo
    {
        return $this->belongsTo(ProductStandardCost::class, 'standard_cost_id');
    }
}
