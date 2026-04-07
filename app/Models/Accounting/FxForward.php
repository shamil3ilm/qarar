<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FxForward extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'fx_forwards';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'notional_amount'       => 'decimal:4',
            'forward_rate'          => 'decimal:8',
            'settlement_rate'       => 'decimal:8',
            'settlement_gain_loss'  => 'decimal:4',
            'trade_date'            => 'date',
            'maturity_date'         => 'date',
            'settled_at'            => 'date',
        ];
    }

    public function hedgeRelation(): HasOne
    {
        return $this->hasOne(FxHedgeRelation::class, 'fx_forward_id');
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(FxValuation::class, 'fx_forward_id')->orderBy('valuation_date');
    }

    public function latestValuation(): HasOne
    {
        return $this->hasOne(FxValuation::class, 'fx_forward_id')->latestOfMany('valuation_date');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Notional value in sell_currency (buy side notional × forward rate).
     */
    public function notionalInSellCurrency(): float
    {
        return round((float) $this->notional_amount * (float) $this->forward_rate, 4);
    }
}
