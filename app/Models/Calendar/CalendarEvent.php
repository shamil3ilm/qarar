<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'calendar_events';

    public const TYPE_EVENT = 'event';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_TASK = 'task';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_HOLIDAY = 'holiday';

    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VISIBILITY_DEFAULT = 'default';
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'organization_id',
        'calendar_id',
        'created_by',
        'title',
        'description',
        'location',
        'event_type',
        'start_at',
        'end_at',
        'is_all_day',
        'timezone',
        'status',
        'visibility',
        'color',
        'related_type',
        'related_id',
        'attendees',
        'is_recurring',
        'recurring_event_id',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_all_day' => 'boolean',
            'is_recurring' => 'boolean',
            'attendees' => 'array',
        ];
    }

    // Relationships

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function recurringRule(): HasOne
    {
        return $this->hasOne(CalendarRecurringRule::class, 'event_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurring_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(self::class, 'recurring_event_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(CalendarEventAttendee::class, 'event_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarEventReminder::class, 'event_id');
    }

    // Scopes

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_at', [$startDate, $endDate])
                ->orWhereBetween('end_at', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_at', '<=', $startDate)
                        ->where('end_at', '>=', $endDate);
                });
        });
    }

    public function scopeForCalendar($query, int $calendarId)
    {
        return $query->where('calendar_id', $calendarId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_at', '>=', now())->orderBy('start_at');
    }

    public function scopeAllDay($query)
    {
        return $query->where('is_all_day', true);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    // Helpers

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isTentative(): bool
    {
        return $this->status === self::STATUS_TENTATIVE;
    }

    public function isAllDay(): bool
    {
        return $this->is_all_day;
    }

    public function isRecurring(): bool
    {
        return $this->is_recurring;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function getDurationInMinutes(): ?int
    {
        if (!$this->end_at) {
            return null;
        }

        return (int) $this->start_at->diffInMinutes($this->end_at);
    }
}
