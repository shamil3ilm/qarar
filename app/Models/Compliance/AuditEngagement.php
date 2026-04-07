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

class AuditEngagement extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'grc_audit_engagements';

    public const TYPE_INTERNAL     = 'internal';
    public const TYPE_EXTERNAL     = 'external';
    public const TYPE_REGULATORY   = 'regulatory';
    public const TYPE_IT           = 'it';
    public const TYPE_OPERATIONAL  = 'operational';
    public const TYPE_FINANCIAL    = 'financial';
    public const TYPE_COMPLIANCE   = 'compliance';

    public const STATUS_PLANNING  = 'planning';
    public const STATUS_FIELDWORK = 'fieldwork';
    public const STATUS_REVIEW    = 'review';
    public const STATUS_ISSUED    = 'issued';
    public const STATUS_CLOSED    = 'closed';

    protected $fillable = [
        'organization_id',
        'engagement_number',
        'title',
        'audit_type',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'scope',
        'objectives',
        'lead_auditor_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date'   => 'date',
            'actual_start_date'  => 'date',
            'actual_end_date'    => 'date',
        ];
    }

    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class, 'engagement_id');
    }
}
