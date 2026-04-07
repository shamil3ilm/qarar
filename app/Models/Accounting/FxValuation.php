<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxValuation extends Model
{
    protected $table = 'fx_valuations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valuation_date'      => 'date',
            'spot_rate'           => 'decimal:8',
            'fair_value'          => 'decimal:4',
            'fair_value_change'   => 'decimal:4',
            'effective_portion'   => 'decimal:4',
            'ineffective_portion' => 'decimal:4',
        ];
    }

    public function fxForward(): BelongsTo
    {
        return $this->belongsTo(FxForward::class, 'fx_forward_id');
    }
}
