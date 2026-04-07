<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftPattern extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'shift_patterns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'days_of_week' => 'array',
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rosterLines(): HasMany
    {
        return $this->hasMany(ShiftRosterLine::class, 'shift_pattern_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDurationMinutes(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        $minutes = $start->diffInMinutes($end);

        if ($this->crosses_midnight) {
            $minutes = 1440 - $start->diffInMinutes($end, false);
        }

        return (int) $minutes - $this->break_minutes;
    }
}
