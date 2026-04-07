<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditReport extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'audit_plan_id', 'report_date', 'executive_summary',
        'conclusions', 'overall_rating',
    ];

    protected $casts = ['report_date' => 'date'];

    public function auditPlan(): BelongsTo
    {
        return $this->belongsTo(AuditPlan::class);
    }
}
