<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoReposting extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    // from_type / to_type values
    public const FROM_COST_CENTER    = 'cost_center';
    public const FROM_INTERNAL_ORDER = 'internal_order';
    public const FROM_PROFIT_CENTER  = 'profit_center';

    // Aliases for symmetry
    public const TO_COST_CENTER    = 'cost_center';
    public const TO_INTERNAL_ORDER = 'internal_order';
    public const TO_PROFIT_CENTER  = 'profit_center';

    // status values
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'organization_id',
        'reposting_number',
        'posting_date',
        'document_date',
        'period',
        'fiscal_year',
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'cost_element_id',
        'amount',
        'currency_code',
        'narration',
        'status',
        'reversed_by_id',
        'posted_by',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'posting_date'  => 'date',
            'document_date' => 'date',
            'period'        => 'integer',
            'fiscal_year'   => 'integer',
            'amount'        => 'decimal:4',
            'reversed_at'   => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(CoReposting::class, 'reversed_by_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\Organization::class, 'organization_id');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }
}
