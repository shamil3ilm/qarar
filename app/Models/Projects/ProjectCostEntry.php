<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCostEntry extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // ── Cost type constants ───────────────────────────────────────────────────
    public const TYPE_LABOR = 'labor';
    public const TYPE_MATERIAL = 'material';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_SUBCONTRACT = 'subcontract';
    public const TYPE_OVERHEAD = 'overhead';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'project_id',
        'wbs_element_id',
        'cost_type',
        'description',
        'amount',
        'currency_code',
        'cost_date',
        'reference_type',
        'reference_id',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost_date' => 'date',
        'reference_id' => 'integer',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
