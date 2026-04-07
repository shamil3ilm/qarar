<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CalendarEventAttendee extends Model
{
    use HasFactory;
    protected $table = 'calendar_event_attendees';

    public const ROLE_ORGANIZER = 'organizer';
    public const ROLE_ATTENDEE = 'attendee';
    public const ROLE_OPTIONAL = 'optional';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_TENTATIVE = 'tentative';

    protected $fillable = [
        'event_id',
        'user_id',
        'email',
        'name',
        'role',
        'status',
        'comment',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', self::STATUS_DECLINED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helpers

    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }

    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->name ?? $this->email ?? 'Unknown';
    }
}
