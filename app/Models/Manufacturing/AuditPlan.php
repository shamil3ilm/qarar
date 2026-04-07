<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditPlan extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'plan_number', 'title', 'audit_type',
        'planned_start', 'planned_end', 'lead_auditor_id', 'status',
        'scope', 'objectives',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end'   => 'date',
    ];

    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(AuditChecklist::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function report(): HasMany
    {
        return $this->hasMany(AuditReport::class);
    }
}
