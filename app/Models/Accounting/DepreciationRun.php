<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepreciationRun extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_POSTED,
        self::STATUS_REVERSED,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_depreciation' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function lines(): HasMany
    {
        return $this->hasMany(DepreciationRunLine::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }
}
