<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatReturnLineItem extends Model
{
    protected $table = 'vat_return_line_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'adjustment' => 'decimal:2',
        ];
    }

    public function vatReturnPeriod(): BelongsTo
    {
        return $this->belongsTo(VatReturnPeriod::class, 'vat_return_period_id');
    }
}
