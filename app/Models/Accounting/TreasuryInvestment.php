<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryInvestment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE        = 'active';
    public const STATUS_MATURED       = 'matured';
    public const STATUS_PRE_LIQUIDATED = 'pre_liquidated';
    public const STATUS_ROLLED_OVER   = 'rolled_over';

    public const TYPE_FIXED_DEPOSIT  = 'fixed_deposit';
    public const TYPE_MONEY_MARKET   = 'money_market';
    public const TYPE_BOND           = 'bond';
    public const TYPE_TREASURY_BILL  = 'treasury_bill';
    public const TYPE_MUTUAL_FUND    = 'mutual_fund';

    protected $fillable = [
        'organization_id',
        'instrument_number',
        'instrument_type',
        'counterparty',
        'principal_amount',
        'interest_rate',
        'investment_date',
        'maturity_date',
        'currency_code',
        'bank_account_id',
        'accrued_interest',
        'maturity_value',
        'status',
        'gl_account_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'investment_date'  => 'date',
            'maturity_date'    => 'date',
            'principal_amount' => 'decimal:4',
            'interest_rate'    => 'decimal:4',
            'accrued_interest' => 'decimal:4',
            'maturity_value'   => 'decimal:4',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
