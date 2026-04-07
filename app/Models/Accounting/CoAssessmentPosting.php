<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoAssessmentPosting extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'assessment_cycle_id',
        'fiscal_year',
        'period',
        'sender_cost_center_id',
        'receiver_cost_center_id',
        'cost_element_id',
        'amount',
        'currency',
        'reversal_id',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'period'      => 'integer',
            'amount'      => 'float',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function assessmentCycle(): BelongsTo
    {
        return $this->belongsTo(CoAssessmentCycle::class, 'assessment_cycle_id');
    }

    public function senderCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'sender_cost_center_id');
    }

    public function receiverCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'receiver_cost_center_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(CoAssessmentPosting::class, 'reversal_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isReversal(): bool
    {
        return $this->reversal_id !== null;
    }
}
