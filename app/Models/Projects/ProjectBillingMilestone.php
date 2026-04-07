<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBillingMilestone extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'project_billing_rule_id', 'milestone_name', 'billing_amount',
        'billing_percentage', 'due_date', 'invoice_id', 'status', 'invoiced_at',
    ];

    protected $casts = [
        'billing_amount'     => 'decimal:4',
        'billing_percentage' => 'decimal:2',
        'due_date'           => 'date',
        'invoiced_at'        => 'datetime',
    ];

    public function billingRule(): BelongsTo
    {
        return $this->belongsTo(ProjectBillingRule::class, 'project_billing_rule_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
