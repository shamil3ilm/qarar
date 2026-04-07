<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoActivityConfirmation extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REVERSED  = 'reversed';

    protected $fillable = [
        'organization_id',
        'confirmation_number',
        'work_order_id',
        'work_center_id',
        'cost_center_id',
        'activity_type_id',
        'confirmed_quantity',
        'planned_quantity',
        'uom',
        'actual_rate',
        'planned_rate',
        'actual_cost',
        'fiscal_year',
        'period',
        'confirmation_date',
        'confirmed_by',
        'status',
        'reversal_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_quantity' => 'float',
            'planned_quantity'   => 'float',
            'actual_rate'        => 'float',
            'planned_rate'       => 'float',
            'actual_cost'        => 'float',
            'fiscal_year'        => 'integer',
            'period'             => 'integer',
            'confirmation_date'  => 'date',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function reversalConfirmation(): BelongsTo
    {
        return $this->belongsTo(CoActivityConfirmation::class, 'reversal_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }
}
