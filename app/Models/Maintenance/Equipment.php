<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use HasAuditTrail;
    use SoftDeletes;

    public const STATUS_ACTIVE            = 'active';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_DECOMMISSIONED    = 'decommissioned';
    public const STATUS_SCRAPPED          = 'scrapped';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_DECOMMISSIONED,
        self::STATUS_SCRAPPED,
    ];

    protected $table = 'equipment';

    protected $fillable = [
        'organization_id',
        'functional_location_id',
        'equipment_category_id',
        'equipment_number',
        'name',
        'description',
        'manufacturer',
        'model',
        'serial_number',
        'acquisition_date',
        'acquisition_cost',
        'warranty_expiry',
        'status',
        'last_maintenance_date',
        'next_maintenance_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date'      => 'date',
            'warranty_expiry'       => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'acquisition_cost'      => 'decimal:2',
        ];
    }

    // Relations

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'functional_location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class, 'equipment_id');
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'equipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Computed helpers

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_UNDER_MAINTENANCE;
    }

    /**
     * Returns the number of days until the next scheduled maintenance,
     * or null when no maintenance date is set. Negative values indicate overdue.
     */
    public function getDaysUntilNextMaintenance(): ?int
    {
        if ($this->next_maintenance_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->next_maintenance_date->startOfDay(),
            false
        );
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', now()->addDays($days)->toDateString());
    }
}
