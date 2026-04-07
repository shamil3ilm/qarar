<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingSheetRunResult extends Model
{
    protected $fillable = [
        'costing_sheet_run_id',
        'costing_sheet_row_id',
        'base_amount',
        'overhead_rate',
        'overhead_amount',
        'credit_posted',
    ];

    protected function casts(): array
    {
        return [
            'base_amount'     => 'decimal:4',
            'overhead_rate'   => 'decimal:6',
            'overhead_amount' => 'decimal:4',
            'credit_posted'   => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function run(): BelongsTo
    {
        return $this->belongsTo(CostingSheetRun::class, 'costing_sheet_run_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(CostingSheetRow::class, 'costing_sheet_row_id');
    }
}
