<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashFund extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'petty_cash_funds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_balance'       => 'decimal:4',
            'current_balance'       => 'decimal:4',
            'max_transaction_limit' => 'decimal:4',
            'is_active'             => 'boolean',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'account_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class, 'fund_id');
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(PettyCashReplenishment::class, 'fund_id');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }
}
