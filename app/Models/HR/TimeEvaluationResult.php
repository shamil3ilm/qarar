<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEvaluationResult extends Model
{
    protected $fillable = [
        'time_sheet_id',
        'employee_id',
        'evaluation_date',
        'wage_type_id',
        'hours',
        'amount',
        'currency_code',
        'transferred_to_payroll',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date'       => 'date',
            'hours'                 => 'decimal:2',
            'amount'                => 'decimal:4',
            'transferred_to_payroll' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function timeSheet(): BelongsTo
    {
        return $this->belongsTo(TimeSheet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(TimeWageType::class, 'wage_type_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeNotTransferred(Builder $query): Builder
    {
        return $query->where('transferred_to_payroll', false);
    }

    public function scopeTransferred(Builder $query): Builder
    {
        return $query->where('transferred_to_payroll', true);
    }
}
