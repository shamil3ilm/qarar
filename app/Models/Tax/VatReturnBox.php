<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VatReturnBox extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'output_amount' => 'decimal:4',
            'input_amount'  => 'decimal:4',
            'net_vat'       => 'decimal:4',
        ];
    }

    public function returnPeriod(): BelongsTo
    {
        return $this->belongsTo(VatReturnPeriod::class, 'vat_return_period_id');
    }
}
