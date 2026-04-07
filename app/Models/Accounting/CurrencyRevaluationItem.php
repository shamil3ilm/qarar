<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRevaluationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function revaluation(): BelongsTo
    {
        return $this->belongsTo(CurrencyRevaluation::class, 'currency_revaluation_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Contact::class, 'contact_id');
    }
}