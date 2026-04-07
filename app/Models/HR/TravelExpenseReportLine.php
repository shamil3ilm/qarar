<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelExpenseReportLine extends Model
{
    protected $table = 'travel_expense_report_lines';

    protected $fillable = [
        'expense_report_id',
        'expense_type_id',
        'expense_date',
        'description',
        'amount',
        'currency_code',
        'amount_in_local',
        'receipt_attached',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'expense_date'    => 'date',
            'amount'          => 'decimal:4',
            'amount_in_local' => 'decimal:4',
            'receipt_attached' => 'boolean',
        ];
    }

    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(TravelExpenseReport::class, 'expense_report_id');
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(TravelExpenseType::class, 'expense_type_id');
    }
}
