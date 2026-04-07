<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidityPlan extends Model
{
    use HasFactory;

    public const GRANULARITY_DAILY   = 'daily';
    public const GRANULARITY_WEEKLY  = 'weekly';
    public const GRANULARITY_MONTHLY = 'monthly';

    protected $fillable = [
        'organization_id',
        'plan_name',
        'plan_from',
        'plan_to',
        'granularity',
    ];

    protected function casts(): array
    {
        return [
            'plan_from' => 'date',
            'plan_to'   => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LiquidityPlanLine::class);
    }
}
