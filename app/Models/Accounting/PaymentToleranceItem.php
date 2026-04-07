<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentToleranceItem extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'underpay_abs' => 'decimal:4',
            'underpay_pct' => 'decimal:4',
            'overpay_abs'  => 'decimal:4',
            'overpay_pct'  => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function toleranceGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentToleranceGroup::class, 'tolerance_group_id');
    }

    public function underpayGlAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'underpay_gl_account_id');
    }

    public function overpayGlAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'overpay_gl_account_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether a given underpayment difference is within tolerance.
     *
     * @param  float  $difference   Positive amount: how much less was paid
     * @param  float  $invoiceAmount  The original invoice gross
     */
    public function isUnderpayWithin(float $difference, float $invoiceAmount): bool
    {
        if ($difference <= 0) {
            return true;
        }

        $withinAbs = (float) $this->underpay_abs >= $difference;
        $withinPct = $invoiceAmount > 0
            && ((float) $this->underpay_pct / 100 * $invoiceAmount) >= $difference;

        return $withinAbs || $withinPct;
    }

    /**
     * Check whether a given overpayment difference is within tolerance.
     *
     * @param  float  $difference  Positive amount: how much more was paid
     */
    public function isOverpayWithin(float $difference, float $invoiceAmount): bool
    {
        if ($difference <= 0) {
            return true;
        }

        $withinAbs = (float) $this->overpay_abs >= $difference;
        $withinPct = $invoiceAmount > 0
            && ((float) $this->overpay_pct / 100 * $invoiceAmount) >= $difference;

        return $withinAbs || $withinPct;
    }
}
