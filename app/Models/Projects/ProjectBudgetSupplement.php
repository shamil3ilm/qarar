<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetSupplement extends Model
{
    use BelongsToOrganization, HasUuid;

    public const TYPE_SUPPLEMENT   = 'supplement';
    public const TYPE_RETURN       = 'return';
    public const TYPE_TRANSFER_IN  = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'project_budget_version_id',
        'wbs_element_id',
        'supplement_type',
        'amount',
        'reason',
        'reference_number',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'amount'      => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetVersion::class, 'project_budget_version_id');
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
