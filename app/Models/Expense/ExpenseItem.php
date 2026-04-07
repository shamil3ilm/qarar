<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExpenseItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'expense_id',
        'category_id',
        'description',
        'amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'account_id',
        'line_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'line_order' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
