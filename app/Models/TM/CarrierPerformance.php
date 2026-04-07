<?php

declare(strict_types=1);

namespace App\Models\TM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierPerformance extends Model
{
    protected $table = 'tm_carrier_performance';

    protected $fillable = [
        'organization_id',
        'carrier_id',
        'period_year',
        'period_month',
        'total_shipments',
        'on_time_deliveries',
        'late_deliveries',
        'damaged_shipments',
        'lost_shipments',
        'avg_transit_days',
        'cost_variance_pct',
        'on_time_pct',
        'rating',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_shipments' => 'integer',
        'on_time_deliveries' => 'integer',
        'late_deliveries' => 'integer',
        'damaged_shipments' => 'integer',
        'lost_shipments' => 'integer',
        'avg_transit_days' => 'decimal:2',
        'cost_variance_pct' => 'decimal:2',
        'on_time_pct' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }
}
