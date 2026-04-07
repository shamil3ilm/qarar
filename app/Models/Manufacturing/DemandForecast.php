<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandForecast extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'product_id',
        'warehouse_id',
        'forecast_date',
        'forecast_quantity',
        'actual_quantity',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date'     => 'date',
            'forecast_quantity' => 'decimal:4',
            'actual_quantity'   => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate forecast accuracy as a percentage.
     * Returns null when actual quantity is not yet set.
     */
    public function getAccuracy(): ?float
    {
        if ($this->actual_quantity === null) {
            return null;
        }

        $actual = (float) $this->actual_quantity;

        if ($actual === 0.0) {
            return 0.0;
        }

        $forecast = (float) $this->forecast_quantity;
        $accuracy = (1 - abs($forecast - $actual) / $actual) * 100;

        return round(max(0.0, $accuracy), 2);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('forecast_date', [$from, $to]);
    }
}
