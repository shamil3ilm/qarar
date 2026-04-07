<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarRecurringRule extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'days_of_week'        => 'array',
        'days_of_month'       => 'array',
        'months_of_year'      => 'array',
        'exception_dates'     => 'array',
        'interval'            => 'integer',
        'ends_at'             => 'date',
        'max_occurrences'     => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }
}