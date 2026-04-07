<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostingSheetRow extends Model
{
    use HasUuid;

    public const TYPE_BASE     = 'base';
    public const TYPE_OVERHEAD = 'overhead';
    public const TYPE_CREDIT   = 'credit';

    public const TYPES = [
        self::TYPE_BASE,
        self::TYPE_OVERHEAD,
        self::TYPE_CREDIT,
    ];

    protected $fillable = [
        'costing_sheet_id',
        'row_type',
        'description',
        'sort_order',
        'base_cost_element_id',
        'overhead_key_id',
        'credit_cost_center_id',
        'credit_cost_element_id',
        'from_row',
        'to_row',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'from_row'   => 'integer',
            'to_row'     => 'integer',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costingSheet(): BelongsTo
    {
        return $this->belongsTo(CostingSheet::class, 'costing_sheet_id');
    }

    public function baseCostElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'base_cost_element_id');
    }

    public function overheadKey(): BelongsTo
    {
        return $this->belongsTo(OverheadKey::class, 'overhead_key_id');
    }

    public function creditCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'credit_cost_center_id');
    }

    public function creditCostElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'credit_cost_element_id');
    }

    public function runResults(): HasMany
    {
        return $this->hasMany(CostingSheetRunResult::class, 'costing_sheet_row_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isBase(): bool
    {
        return $this->row_type === self::TYPE_BASE;
    }

    public function isOverhead(): bool
    {
        return $this->row_type === self::TYPE_OVERHEAD;
    }

    public function isCredit(): bool
    {
        return $this->row_type === self::TYPE_CREDIT;
    }
}
