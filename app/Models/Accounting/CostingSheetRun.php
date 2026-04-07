<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostingSheetRun extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR     = 'error';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_ERROR,
    ];

    protected $fillable = [
        'organization_id',
        'costing_sheet_id',
        'reference_type',
        'reference_id',
        'run_date',
        'total_overhead',
        'currency_code',
        'status',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'run_date'       => 'datetime',
            'total_overhead' => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costingSheet(): BelongsTo
    {
        return $this->belongsTo(CostingSheet::class, 'costing_sheet_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CostingSheetRunResult::class, 'costing_sheet_run_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
