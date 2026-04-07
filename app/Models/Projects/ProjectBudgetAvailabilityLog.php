<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetAvailabilityLog extends Model
{
    protected $fillable = [
        'organization_id',
        'project_budget_line_item_id',
        'wbs_element_id',
        'document_type',
        'document_id',
        'requested_amount',
        'available_amount',
        'result',
        'message',
        'checked_at',
        'checked_by',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:4',
        'available_amount' => 'decimal:4',
        'checked_at'       => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLineItem::class, 'project_budget_line_item_id');
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
