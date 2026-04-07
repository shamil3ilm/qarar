<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostCenterAssignment extends Model
{
    protected $fillable = [
        'organization_id',
        'assignable_type',
        'assignable_id',
        'cost_center_id',
        'profit_center_id',
        'split_percent',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'split_percent'  => 'decimal:2',
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    /**
     * The assignable model (Employee, Department, etc.).
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /**
     * Optional profit center linked to this assignment.
     */
    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
