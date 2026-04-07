<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityDeposit extends Model
{
    use HasUuid;

    protected $table = 're_security_deposits';

    protected $fillable = [
        'organization_id',
        'contract_id',
        'deposit_number',
        'required_amount',
        'collected_amount',
        'currency_code',
        'collected_date',
        'interest_rate_pct',
        'accrued_interest',
        'status',
        'refunded_amount',
        'refund_date',
        'refund_reason',
    ];

    protected $casts = [
        'required_amount' => 'decimal:4',
        'collected_amount' => 'decimal:4',
        'interest_rate_pct' => 'decimal:4',
        'accrued_interest' => 'decimal:4',
        'refunded_amount' => 'decimal:4',
        'collected_date' => 'date',
        'refund_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }

    /** Compute interest accrued since collected_date to today. */
    public function computeCurrentInterest(): string
    {
        if (! $this->collected_date || (float) $this->interest_rate_pct === 0.0) {
            return '0.0000';
        }

        $days = $this->collected_date->diffInDays(now());
        $annual = bcmul((string) $this->collected_amount, bcdiv((string) $this->interest_rate_pct, '100', 8), 8);
        $daily = bcdiv($annual, '365', 8);

        return bcmul($daily, (string) $days, 4);
    }
}
