<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbsElement extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    // ── Status constants ──────────────────────────────────────────────────────
    public const STATUS_CREATED = 'created';
    public const STATUS_RELEASED = 'released';
    public const STATUS_TECHNICALLY_COMPLETE = 'technically_complete';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'project_id',
        'parent_id',
        'wbs_code',
        'name',
        'description',
        'status',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'planned_cost',
        'actual_cost',
        'planned_revenue',
        'actual_revenue',
        'responsible_employee_id',
        'progress_percent',
        'sort_order',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'planned_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'planned_revenue' => 'decimal:2',
        'actual_revenue' => 'decimal:2',
        'progress_percent' => 'integer',
        'sort_order' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WbsElement::class, 'parent_id')->orderBy('sort_order');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class);
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(ProjectCostEntry::class);
    }

    // ── Cost aggregation helpers ──────────────────────────────────────────────

    /**
     * Own planned cost + sum of all children planned costs (recursive).
     */
    public function getTotalPlannedCost(): float
    {
        $own = (float) $this->planned_cost;
        $childrenCost = $this->children->sum(fn (WbsElement $child) => $child->getTotalPlannedCost());

        return $own + (float) $childrenCost;
    }

    /**
     * Own actual cost + sum of all children actual costs (recursive).
     */
    public function getTotalActualCost(): float
    {
        $own = (float) $this->actual_cost;
        $childrenCost = $this->children->sum(fn (WbsElement $child) => $child->getTotalActualCost());

        return $own + (float) $childrenCost;
    }

    // ── WBS code generation ───────────────────────────────────────────────────

    /**
     * Generate the next WBS code for a project/parent combination.
     *
     * Root elements get codes like 1, 2, 3...
     * Children get codes like 1.1, 1.2, 2.1, 2.1.1 etc.
     */
    public static function getNextCode(Project $project, ?int $parentId = null): string
    {
        $siblings = static::withoutGlobalScope('organization')
            ->where('project_id', $project->id)
            ->where('parent_id', $parentId)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($parentId === null) {
            // Root level: count existing root elements
            $count = static::withoutGlobalScope('organization')
                ->where('project_id', $project->id)
                ->whereNull('parent_id')
                ->count();

            return (string) ($count + 1);
        }

        $parent = static::withoutGlobalScope('organization')->find($parentId);

        if ($parent === null) {
            return '1';
        }

        $childCount = static::withoutGlobalScope('organization')
            ->where('project_id', $project->id)
            ->where('parent_id', $parentId)
            ->count();

        return $parent->wbs_code . '.' . ($childCount + 1);
    }

    // ── Boolean helpers ───────────────────────────────────────────────────────

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }
}
