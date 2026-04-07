<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentSparePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'product_id',
        'recommended_stock_qty',
        'current_stock_qty',
        'is_critical',
        'lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'recommended_stock_qty' => 'decimal:4',
            'current_stock_qty'     => 'decimal:4',
            'lead_time_days'        => 'decimal:2',
            'is_critical'           => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isStockSufficient(): bool
    {
        return (float) $this->current_stock_qty >= (float) $this->recommended_stock_qty;
    }

    public function getStockDeficit(): float
    {
        return max(0.0, (float) $this->recommended_stock_qty - (float) $this->current_stock_qty);
    }

    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    public function scopeForEquipment($query, int $equipmentId)
    {
        return $query->where('equipment_id', $equipmentId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock_qty < recommended_stock_qty');
    }
}
