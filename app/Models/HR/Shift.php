<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'shifts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'is_overnight' => 'boolean',
            'is_flexible' => 'boolean',
            'flexible_start_window_minutes' => 'integer',
            'overtime_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate net working minutes excluding breaks.
     */
    public function getNetMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        $minutes = $this->is_overnight
            ? $start->diffInMinutes($end->addDay())
            : $start->diffInMinutes($end);

        return max(0, $minutes - $this->break_minutes);
    }
}
