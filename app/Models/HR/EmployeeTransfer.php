<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTransfer extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    // Transfer types
    public const TYPE_DEPARTMENT  = 'department';
    public const TYPE_POSITION    = 'position';
    public const TYPE_DESIGNATION = 'designation';
    public const TYPE_LOCATION    = 'location';
    public const TYPE_MANAGER     = 'manager';
    public const TYPE_LATERAL     = 'lateral';
    public const TYPE_PROMOTION   = 'promotion';
    public const TYPE_DEMOTION    = 'demotion';

    // Statuses
    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED         = 'approved';
    public const STATUS_REJECTED         = 'rejected';
    public const STATUS_APPLIED          = 'applied';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'transfer_number',
        'effective_date',
        'transfer_type',
        'reason',
        'from_department_id',
        'to_department_id',
        'from_designation_id',
        'to_designation_id',
        'from_position_id',
        'to_position_id',
        'from_reporting_manager_id',
        'to_reporting_manager_id',
        'from_branch_id',
        'to_branch_id',
        'status',
        'initiated_by',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejected_at',
        'applied_at',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'approved_at'    => 'datetime',
            'rejected_at'    => 'datetime',
            'applied_at'     => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'from_designation_id');
    }

    public function toDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'to_designation_id');
    }

    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position_id');
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    public function fromManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_reporting_manager_id');
    }

    public function toManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_reporting_manager_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
