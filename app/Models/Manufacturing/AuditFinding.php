<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'audit_plan_id', 'finding_number', 'finding_type',
        'description', 'requirement_reference', 'evidence', 'status', 'due_date',
    ];

    protected $casts = ['due_date' => 'date'];

    public function auditPlan(): BelongsTo
    {
        return $this->belongsTo(AuditPlan::class);
    }
}
