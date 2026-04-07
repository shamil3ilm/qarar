<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePermit extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_HOT_WORK             = 'hot_work';
    public const TYPE_CONFINED_SPACE       = 'confined_space';
    public const TYPE_ELECTRICAL_ISOLATION = 'electrical_isolation';
    public const TYPE_HEIGHT_WORK          = 'height_work';
    public const TYPE_CHEMICAL             = 'chemical';
    public const TYPE_GENERAL              = 'general';

    public const STATUS_REQUESTED  = 'requested';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_SUSPENDED  = 'suspended';
    public const STATUS_CLOSED     = 'closed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'organization_id',
        'maintenance_order_id',
        'permit_number',
        'permit_type',
        'status',
        'valid_from',
        'valid_until',
        'location',
        'work_description',
        'hazards_identified',
        'precautions_required',
        'requested_by',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at'   => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function safetyChecks(): HasMany
    {
        return $this->hasMany(PermitSafetyCheck::class)->orderBy('sort_order');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
