<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmCounter extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'counter_name', 'equipment_id', 'floc_id',
        'uom', 'current_reading', 'overflow_value', 'active',
    ];

    protected $casts = [
        'current_reading' => 'decimal:3',
        'overflow_value'  => 'decimal:3',
        'active'          => 'boolean',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(FlocEquipment::class, 'equipment_id');
    }

    public function functionalLocation(): BelongsTo
    {
        return $this->belongsTo(FunctionalLocation::class, 'floc_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(PmCounterReading::class, 'counter_id');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(PmMaintenancePlan::class, 'counter_id');
    }
}
