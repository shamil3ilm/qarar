<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRosterLine extends Model
{
    use HasUuid;

    protected $table = 'shift_roster_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'is_day_off' => 'boolean',
        ];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(ShiftRoster::class, 'roster_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'shift_pattern_id');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('shift_date', $date);
    }

    public function scopeInDateRange($query, string $start, string $end)
    {
        return $query->whereBetween('shift_date', [$start, $end]);
    }
}
