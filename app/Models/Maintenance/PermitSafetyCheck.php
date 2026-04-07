<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermitSafetyCheck extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'maintenance_permit_id',
        'check_description',
        'is_mandatory',
        'is_completed',
        'completed_by',
        'completed_at',
        'remarks',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory'  => 'boolean',
        'is_completed'  => 'boolean',
        'completed_at'  => 'datetime',
        'sort_order'    => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(MaintenancePermit::class, 'maintenance_permit_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
