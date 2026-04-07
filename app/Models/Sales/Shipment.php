<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'delivery_mode_id', 'shipment_number', 'source_type',
        'source_id', 'contact_id', 'shipping_address', 'billing_address',
        'tracking_number', 'carrier', 'tracking_url', 'ship_date',
        'estimated_delivery', 'actual_delivery', 'total_weight_kg', 'dimensions',
        'shipping_cost', 'currency_code', 'status', 'notes', 'delivery_notes',
        'proof_of_delivery_path', 'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->shipment_number)) {
                $prefix = 'SHP';
                $count = static::where('organization_id', $model->organization_id)->count() + 1;
                $model->shipment_number = $prefix . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->status)) {
                $model->status = 'pending';
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'dimensions' => 'array',
        'ship_date' => 'date',
        'estimated_delivery' => 'date',
        'actual_delivery' => 'date',
        'total_weight_kg' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

    public function deliveryMode(): BelongsTo
    {
        return $this->belongsTo(DeliveryMode::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
