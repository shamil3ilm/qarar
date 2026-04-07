<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountLine extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'cycle_count_session_id', 'product_id', 'warehouse_location_id',
        'system_quantity', 'counted_quantity', 'variance_percentage',
        'recount_required', 'status', 'approved_by',
    ];

    protected $casts = [
        'system_quantity'    => 'decimal:4',
        'counted_quantity'   => 'decimal:4',
        'variance_percentage' => 'decimal:4',
        'recount_required'   => 'boolean',
    ];

    public function getVarianceAttribute(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }
        return (float) $this->counted_quantity - (float) $this->system_quantity;
    }

    public function session(): BelongsTo           { return $this->belongsTo(CycleCountSession::class, 'cycle_count_session_id'); }
    public function product(): BelongsTo           { return $this->belongsTo(Product::class); }
    public function warehouseLocation(): BelongsTo { return $this->belongsTo(WarehouseLocation::class); }
    public function approvedBy(): BelongsTo        { return $this->belongsTo(User::class, 'approved_by'); }
}
