<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Accounting\CostCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeSheetEntry extends Model
{
    public const TYPE_REGULAR  = 'regular';
    public const TYPE_OVERTIME = 'overtime';
    public const TYPE_ABSENCE  = 'absence';
    public const TYPE_HOLIDAY  = 'holiday';
    public const TYPE_TRAINING = 'training';

    protected $fillable = [
        'time_sheet_id',
        'entry_date',
        'start_time',
        'end_time',
        'hours',
        'entry_type',
        'wage_type_id',
        'cost_center_id',
        'project_id',
        'wbs_element_id',
        'work_order_id',
        'activity_code',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'hours'      => 'decimal:2',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function timeSheet(): BelongsTo
    {
        return $this->belongsTo(TimeSheet::class);
    }

    public function wageType(): BelongsTo
    {
        return $this->belongsTo(TimeWageType::class, 'wage_type_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('entry_type', $type);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('entry_date', $date);
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function isNightShift(): bool
    {
        if ($this->start_time === null && $this->end_time === null) {
            return false;
        }

        $nightStart = 22; // 22:00
        $nightEnd   = 6;  // 06:00

        if ($this->start_time !== null) {
            $startHour = (int) date('H', strtotime($this->start_time));
            if ($startHour >= $nightStart || $startHour < $nightEnd) {
                return true;
            }
        }

        if ($this->end_time !== null) {
            $endHour = (int) date('H', strtotime($this->end_time));
            if ($endHour >= $nightStart || $endHour < $nightEnd) {
                return true;
            }
        }

        return false;
    }
}
