<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    // ── Type constants ────────────────────────────────────────────────────────
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_RD = 'rd';
    public const TYPE_CAPITAL = 'capital';

    // ── Status constants ──────────────────────────────────────────────────────
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PLANNING = 'planning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // ── Priority constants ────────────────────────────────────────────────────
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'project_number',
        'name',
        'description',
        'project_type',
        'customer_id',
        'status',
        'priority',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'budget',
        'currency_code',
        'manager_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * All WBS elements for this project (root-level only — no parent).
     */
    public function wbsElements(): HasMany
    {
        return $this->hasMany(WbsElement::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * All WBS elements flat (including children).
     */
    public function allWbsElements(): HasMany
    {
        return $this->hasMany(WbsElement::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('due_date');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class)->orderBy('work_date', 'desc');
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(ProjectCostEntry::class)->orderBy('cost_date', 'desc');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /**
     * Budget variance = budget – total actual cost (positive means under budget).
     */
    public function getBudgetVariance(): float
    {
        $actualCost = (float) $this->costEntries()->sum('amount');

        return (float) $this->budget - $actualCost;
    }

    /**
     * Average progress across all WBS elements (weighted by element count).
     */
    public function getCompletionPercent(): float
    {
        $elements = $this->allWbsElements()->get(['progress_percent']);

        if ($elements->isEmpty()) {
            return 0.0;
        }

        return round((float) $elements->avg('progress_percent'), 2);
    }

    // ── Status transition helpers ─────────────────────────────────────────────

    public function activate(int $userId): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'actual_start_date' => $this->actual_start_date ?? now()->toDateString(),
        ]);

        return $this->fresh();
    }

    public function complete(int $userId): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'actual_end_date' => $this->actual_end_date ?? now()->toDateString(),
        ]);

        return $this->fresh();
    }

    // ── Boolean helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeActivated(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PLANNING, self::STATUS_ON_HOLD], true);
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
