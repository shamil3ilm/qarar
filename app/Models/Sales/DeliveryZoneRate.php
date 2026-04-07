<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZoneRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_mode_id', 'zone_id', 'rate', 'additional_item_rate',
        'min_weight', 'max_weight', 'currency_code',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'additional_item_rate' => 'decimal:2',
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
    ];

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(DeliveryMode::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }
}
