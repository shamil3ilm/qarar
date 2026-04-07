<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardMovement extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const TYPE_ARRIVAL = 'arrival';
    public const TYPE_MOVE_TO_DOCK = 'move_to_dock';
    public const TYPE_MOVE_TO_ZONE = 'move_to_zone';
    public const TYPE_DEPARTURE = 'departure';

    protected $fillable = [
        'organization_id',
        'truck_appointment_id',
        'from_zone_id',
        'to_zone_id',
        'from_dock_id',
        'to_dock_id',
        'movement_type',
        'moved_at',
        'moved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function truckAppointment(): BelongsTo
    {
        return $this->belongsTo(TruckAppointment::class);
    }

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(YardZone::class, 'to_zone_id');
    }

    public function fromDock(): BelongsTo
    {
        return $this->belongsTo(DockDoor::class, 'from_dock_id');
    }

    public function toDock(): BelongsTo
    {
        return $this->belongsTo(DockDoor::class, 'to_dock_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
