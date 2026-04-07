<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChecklist extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'audit_plan_id', 'item_number', 'question', 'response', 'remarks',
    ];

    public function auditPlan(): BelongsTo
    {
        return $this->belongsTo(AuditPlan::class);
    }
}
