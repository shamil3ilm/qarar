<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ifrs16Schedule extends Model
{
    protected $table = 're_ifrs16_schedules';

    protected $fillable = [
        'contract_id',
        'period_date',
        'opening_liability',
        'interest_expense',
        'lease_payment',
        'principal_reduction',
        'closing_liability',
        'rou_depreciation',
        'rou_book_value',
        'gl_posted',
    ];

    protected function casts(): array
    {
        return [
            'period_date'        => 'date',
            'opening_liability'  => 'decimal:4',
            'interest_expense'   => 'decimal:4',
            'lease_payment'      => 'decimal:4',
            'principal_reduction'=> 'decimal:4',
            'closing_liability'  => 'decimal:4',
            'rou_depreciation'   => 'decimal:4',
            'rou_book_value'     => 'decimal:4',
            'gl_posted'          => 'boolean',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }
}
