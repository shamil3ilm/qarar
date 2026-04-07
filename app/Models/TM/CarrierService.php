<?php

declare(strict_types=1);

namespace App\Models\TM;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierService extends Model
{
    use HasUuid;

    protected $table = 'tm_carrier_services';

    protected $fillable = [
        'organization_id',
        'carrier_id',
        'code',
        'name',
        'mode',
        'transit_days_min',
        'transit_days_max',
        'is_tracking_available',
        'tracking_url_template',
        'handles_dangerous_goods',
        'handles_refrigerated',
        'handles_oversized',
        'is_active',
    ];

    protected $casts = [
        'transit_days_min' => 'integer',
        'transit_days_max' => 'integer',
        'is_tracking_available' => 'boolean',
        'handles_dangerous_goods' => 'boolean',
        'handles_refrigerated' => 'boolean',
        'handles_oversized' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function getTrackingUrl(string $trackingNumber): ?string
    {
        if (! $this->tracking_url_template) {
            return null;
        }

        return str_replace('{tracking_number}', $trackingNumber, $this->tracking_url_template);
    }
}
