<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitCenterPlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'profit_center_id',
        'fiscal_year',
        'period',
        'plan_revenue',
        'plan_cost',
        'plan_profit',
        'currency_code',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year'  => 'integer',
            'period'       => 'integer',
            'plan_revenue' => 'decimal:4',
            'plan_cost'    => 'decimal:4',
            'plan_profit'  => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function profitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'profit_center_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class, 'organization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
