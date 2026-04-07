<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditFinding extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_audit_findings';

    public const SEVERITY_CRITICAL      = 'critical';
    public const SEVERITY_HIGH          = 'high';
    public const SEVERITY_MEDIUM        = 'medium';
    public const SEVERITY_LOW           = 'low';
    public const SEVERITY_INFORMATIONAL = 'informational';

    public const STATUS_OPEN            = 'open';
    public const STATUS_ASSIGNED        = 'assigned';
    public const STATUS_IN_REMEDIATION  = 'in_remediation';
    public const STATUS_REMEDIATED      = 'remediated';
    public const STATUS_VERIFIED        = 'verified';
    public const STATUS_CLOSED          = 'closed';
    public const STATUS_RISK_ACCEPTED   = 'risk_accepted';

    public const TYPE_CONTROL_DEFICIENCY = 'control_deficiency';
    public const TYPE_PROCESS_GAP        = 'process_gap';
    public const TYPE_POLICY_VIOLATION   = 'policy_violation';
    public const TYPE_FRAUD_RISK         = 'fraud_risk';
    public const TYPE_IT_RISK            = 'it_risk';
    public const TYPE_COMPLIANCE_GAP     = 'compliance_gap';

    protected $fillable = [
        'organization_id',
        'engagement_id',
        'finding_number',
        'title',
        'description',
        'criteria',
        'condition',
        'cause',
        'effect',
        'recommendation',
        'severity',
        'status',
        'finding_type',
        'module_reference',
        'due_date',
        'owner_id',
        'management_response',
        'management_response_date',
        'remediation_plan',
        'remediation_target_date',
        'remediation_completed_date',
        'verification_notes',
        'verified_by',
        'verified_at',
        'repeat_finding',
        'parent_finding_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date'                    => 'date',
            'management_response_date'    => 'date',
            'remediation_target_date'     => 'date',
            'remediation_completed_date'  => 'date',
            'verified_at'                 => 'datetime',
            'repeat_finding'              => 'boolean',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(AuditEngagement::class, 'engagement_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentFinding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'parent_finding_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FindingAction::class, 'finding_id');
    }
}
