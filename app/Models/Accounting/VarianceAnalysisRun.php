<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VarianceAnalysisRun extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const RUN_TYPE_PRODUCTION_ORDER = 'production_order';
    public const RUN_TYPE_COST_CENTER      = 'cost_center';
    public const RUN_TYPE_PROJECT          = 'project';

    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'organization_id',
        'period',
        'fiscal_year',
        'run_type',
        'status',
        'run_by',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'period'       => 'integer',
            'fiscal_year'  => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VarianceAnalysisItem::class, 'variance_analysis_run_id');
    }

    // ----------------------------------------------------------------
    // Business helpers
    // ----------------------------------------------------------------

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
