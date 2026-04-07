<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowForecastLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_date'   => 'date',
            'expected_amount' => 'decimal:2',
            'actual_amount'   => 'decimal:2',
            'is_confirmed'    => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecast::class, 'cash_flow_forecast_id');
    }

    // -------------------------------------------------------------------------
    // Business logic
    // -------------------------------------------------------------------------

    /**
     * Returns the difference between actual and expected amounts.
     * Positive = actual exceeded expectation (good for inflows, bad for outflows).
     * Returns 0.0 when actual_amount has not been recorded yet.
     */
    public function getVariance(): float
    {
        if ($this->actual_amount === null) {
            return 0.0;
        }

        return (float) bcsub(
            (string) $this->actual_amount,
            (string) $this->expected_amount,
            4
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeInflows($query)
    {
        return $query->where('line_type', 'inflow');
    }

    public function scopeOutflows($query)
    {
        return $query->where('line_type', 'outflow');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }
}
