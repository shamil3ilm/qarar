<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidityPlanLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'liquidity_plan_id',
        'period_date',
        'category',
        'flow_type',
        'planned_amount',
        'actual_amount',
        'currency_code',
        'bank_account_id',
    ];

    protected function casts(): array
    {
        return [
            'period_date'    => 'date',
            'planned_amount' => 'decimal:4',
            'actual_amount'  => 'decimal:4',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LiquidityPlan::class, 'liquidity_plan_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
