<?php

declare(strict_types=1);

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractOption extends Model
{
    protected $table = 're_contract_options';

    protected $fillable = [
        'contract_id',
        'option_type',
        'exercise_window_start',
        'exercise_window_end',
        'exercise_deadline',
        'new_term_months',
        'new_rent_amount',
        'status',
        'exercised_at',
        'notes',
    ];

    protected $casts = [
        'exercise_window_start' => 'date',
        'exercise_window_end' => 'date',
        'exercise_deadline' => 'date',
        'new_rent_amount' => 'decimal:4',
        'new_term_months' => 'integer',
        'exercised_at' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'contract_id');
    }

    public function isExercisable(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }
        $today = now()->toDateString();
        if ($this->exercise_deadline < $today) {
            return false;
        }
        if ($this->exercise_window_start && $this->exercise_window_start > $today) {
            return false;
        }

        return true;
    }
}
