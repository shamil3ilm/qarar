<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Accounting\CostElement;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetLineItem extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'project_budget_version_id',
        'wbs_element_id',
        'cost_element_id',
        'budgeted_amount',
        'committed_amount',
        'actual_amount',
        'available_amount',
        'avac_action',
        'tolerance_percent',
    ];

    protected $casts = [
        'budgeted_amount'   => 'decimal:4',
        'committed_amount'  => 'decimal:4',
        'actual_amount'     => 'decimal:4',
        'available_amount'  => 'decimal:4',
        'tolerance_percent' => 'decimal:2',
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

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Recompute available_amount = budgeted - committed - actual and persist.
     */
    public function refreshAvailableAmount(): void
    {
        $this->available_amount = (float) $this->budgeted_amount
            - (float) $this->committed_amount
            - (float) $this->actual_amount;

        $this->save();
    }

    /**
     * Utilization as a percentage of budgeted amount.
     * Returns 0 when budget is zero to avoid division by zero.
     */
    public function getUtilizationPercent(): float
    {
        $budget = (float) $this->budgeted_amount;

        if ($budget <= 0) {
            return 0.0;
        }

        return round(((float) $this->committed_amount + (float) $this->actual_amount) / $budget * 100, 2);
    }

    /**
     * Returns true when (committed + actual) exceeds the tolerance-adjusted budget.
     */
    public function isOverBudget(): bool
    {
        $budget    = (float) $this->budgeted_amount;
        $used      = (float) $this->committed_amount + (float) $this->actual_amount;
        $tolerance = (float) $this->tolerance_percent;

        return $used > $budget * (1 + $tolerance / 100);
    }
}
