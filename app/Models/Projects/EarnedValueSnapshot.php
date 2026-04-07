<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarnedValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'snapshot_date',
        'budget_at_completion',
        'planned_value',
        'earned_value',
        'actual_cost',
        'schedule_variance',
        'cost_variance',
        'schedule_performance_index',
        'cost_performance_index',
        'estimate_at_completion',
        'estimate_to_complete',
        'variance_at_completion',
    ];

    protected $casts = [
        'snapshot_date'               => 'date',
        'budget_at_completion'        => 'decimal:4',
        'planned_value'               => 'decimal:4',
        'earned_value'                => 'decimal:4',
        'actual_cost'                 => 'decimal:4',
        'schedule_variance'           => 'decimal:4',
        'cost_variance'               => 'decimal:4',
        'schedule_performance_index'  => 'decimal:4',
        'cost_performance_index'      => 'decimal:4',
        'estimate_at_completion'      => 'decimal:4',
        'estimate_to_complete'        => 'decimal:4',
        'variance_at_completion'      => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Project is behind schedule when SV < 0.
     */
    public function isBehindSchedule(): bool
    {
        return (float) $this->schedule_variance < 0.0;
    }

    /**
     * Project is over budget when CV < 0.
     */
    public function isOverBudget(): bool
    {
        return (float) $this->cost_variance < 0.0;
    }

    /**
     * Returns a health status string for dashboard display.
     */
    public function getHealthStatus(): string
    {
        $behindSchedule = $this->isBehindSchedule();
        $overBudget     = $this->isOverBudget();

        if ($behindSchedule && $overBudget) {
            return 'critical';
        }

        if ($behindSchedule || $overBudget) {
            return 'warning';
        }

        return 'healthy';
    }
}
