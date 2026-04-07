<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TruckAppointment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_DOCKED = 'docked';
    public const STATUS_LOADING = 'loading';
    public const STATUS_DEPARTED = 'departed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_PICKUP = 'pickup';
    public const TYPE_BOTH = 'both';

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'vendor_id',
        'appointment_number',
        'scheduled_arrival',
        'scheduled_departure',
        'actual_arrival',
        'actual_departure',
        'dock_door_id',
        'yard_zone_id',
        'vehicle_plate',
        'driver_name',
        'driver_phone',
        'appointment_type',
        'reference_type',
        'reference_id',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_arrival'   => 'datetime',
            'scheduled_departure' => 'datetime',
            'actual_arrival'      => 'datetime',
            'actual_departure'    => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function dockDoor(): BelongsTo
    {
        return $this->belongsTo(DockDoor::class);
    }

    public function yardZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(YardMovement::class)->orderBy('moved_at');
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function isDocked(): bool
    {
        return $this->status === self::STATUS_DOCKED;
    }

    public function isDeparted(): bool
    {
        return $this->status === self::STATUS_DEPARTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canCheckIn(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function canAssignDock(): bool
    {
        return in_array($this->status, [self::STATUS_CHECKED_IN, self::STATUS_DOCKED], true);
    }

    public function canDepart(): bool
    {
        return in_array($this->status, [
            self::STATUS_CHECKED_IN,
            self::STATUS_DOCKED,
            self::STATUS_LOADING,
        ], true);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_DEPARTED, self::STATUS_CANCELLED]);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('scheduled_arrival', $date);
    }
}
