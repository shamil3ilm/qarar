<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpense extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    protected $guarded = ['id'];

    // Frequency constants
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_SEMI_ANNUAL = 'semi_annual';
    public const FREQUENCY_ANNUAL = 'annual';

    public const FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_QUARTERLY,
        self::FREQUENCY_SEMI_ANNUAL,
        self::FREQUENCY_ANNUAL,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_occurrence' => 'date',
            'last_processed_at' => 'datetime',
            'is_active' => 'boolean',
            'frequency_interval' => 'integer',
            'occurrences_count' => 'integer',
            'max_occurrences' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Expense\ExpenseCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}