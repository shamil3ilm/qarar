<?php

declare(strict_types=1);

namespace App\Models\Calendar;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $table = 'reminders';

    public const FREQUENCY_ONCE = 'once';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'description',
        'remind_at',
        'frequency',
        'remindable_type',
        'remindable_id',
        'is_sent',
        'sent_at',
        'is_dismissed',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'is_dismissed' => 'boolean',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopeSent($query)
    {
        return $query->where('is_sent', true);
    }

    public function scopeUnsent($query)
    {
        return $query->where('is_sent', false);
    }

    public function scopeDismissed($query)
    {
        return $query->where('is_dismissed', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_sent', false)->where('is_dismissed', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDue($query)
    {
        return $query->active()->where('remind_at', '<=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->active()->where('remind_at', '>', now())->orderBy('remind_at');
    }

    public function scopeWithFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    // Helpers

    public function isSent(): bool
    {
        return $this->is_sent;
    }

    public function isDismissed(): bool
    {
        return $this->is_dismissed;
    }

    public function isActive(): bool
    {
        return !$this->is_sent && !$this->is_dismissed;
    }

    public function isDue(): bool
    {
        return $this->isActive() && $this->remind_at->lte(now());
    }

    public function isOneTime(): bool
    {
        return $this->frequency === self::FREQUENCY_ONCE || $this->frequency === null;
    }

    public function markAsSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    public function dismiss(): void
    {
        $this->update(['is_dismissed' => true]);
    }
}
