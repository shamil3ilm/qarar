<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimeEntry extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'wbs_element_id',
        'employee_id',
        'work_date',
        'hours',
        'description',
        'is_billable',
        'hourly_rate',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'is_billable' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }

    public function getBillableAmount(): float
    {
        if (!$this->is_billable || $this->hourly_rate === null) {
            return 0.0;
        }

        return (float) $this->hours * (float) $this->hourly_rate;
    }
}
