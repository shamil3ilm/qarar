<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowLine extends Model
{
    use HasFactory;

    public const TYPE_INFLOW  = 'inflow';
    public const TYPE_OUTFLOW = 'outflow';

    public const CONFIDENCE_CERTAIN  = 'certain';
    public const CONFIDENCE_PROBABLE = 'probable';
    public const CONFIDENCE_POSSIBLE = 'possible';

    public const SOURCE_INVOICE     = 'invoice';
    public const SOURCE_PURCHASE_ORDER = 'purchase_order';
    public const SOURCE_LOAN        = 'loan';
    public const SOURCE_BANK        = 'bank_account';
    public const SOURCE_MANUAL      = 'manual';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
            'amount'        => 'decimal:4',
            'is_actual'     => 'boolean',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecast::class);
    }

    public function scopeInflows($query)
    {
        return $query->where('flow_type', self::TYPE_INFLOW);
    }

    public function scopeOutflows($query)
    {
        return $query->where('flow_type', self::TYPE_OUTFLOW);
    }

    public function scopeCertain($query)
    {
        return $query->where('confidence', self::CONFIDENCE_CERTAIN);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('expected_date', [$from, $to]);
    }
}
