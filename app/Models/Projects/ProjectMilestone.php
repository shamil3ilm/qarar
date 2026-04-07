<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // ── Status constants ──────────────────────────────────────────────────────
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACHIEVED = 'achieved';
    public const STATUS_MISSED = 'missed';

    protected $fillable = [
        'organization_id',
        'project_id',
        'wbs_element_id',
        'name',
        'description',
        'due_date',
        'status',
        'achieved_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'achieved_at' => 'date',
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

    // ── Actions ───────────────────────────────────────────────────────────────

    public function achieve(int $userId): self
    {
        $this->update([
            'status' => self::STATUS_ACHIEVED,
            'achieved_at' => now()->toDateString(),
        ]);

        return $this->fresh();
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->due_date->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAchieved(): bool
    {
        return $this->status === self::STATUS_ACHIEVED;
    }
}
