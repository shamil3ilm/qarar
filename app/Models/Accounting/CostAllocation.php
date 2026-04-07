<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAllocation extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';

    public const METHOD_FIXED      = 'fixed';
    public const METHOD_PERCENTAGE = 'percentage';
    public const METHOD_ACTIVITY   = 'activity';

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'period_start',
        'period_end',
        'from_cost_center_id',
        'to_cost_center_id',
        'allocation_method',
        'allocation_percent',
        'allocation_amount',
        'description',
        'journal_entry_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start'        => 'date',
            'period_end'          => 'date',
            'allocation_percent'  => 'decimal:2',
            'allocation_amount'   => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function fromCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'from_cost_center_id');
    }

    public function toCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'to_cost_center_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
