<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'position_date',
        'book_balance',
        'available_balance',
        'projected_balance',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'position_date'     => 'date',
            'book_balance'      => 'decimal:4',
            'available_balance' => 'decimal:4',
            'projected_balance' => 'decimal:4',
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
}
