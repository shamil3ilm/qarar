<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class NotificationPreference extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'contact_messaging_preferences';

    // Preferred channels
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_PUSH = 'push_notification';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'push_enabled',
        'marketing_enabled',
        'transactional_enabled',
        'reminder_enabled',
        'preferred_channel',
        'preferred_language',
        'timezone',
        'quiet_hours',
        'unsubscribed_at',
        'unsubscribe_reason',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'marketing_enabled' => 'boolean',
            'transactional_enabled' => 'boolean',
            'reminder_enabled' => 'boolean',
            'quiet_hours' => 'array',
            'unsubscribed_at' => 'datetime',
        ];
    }

    // Relationships

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Scopes

    public function scopeForContact(Builder $query, int $contactId): Builder
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed(Builder $query): Builder
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    public function scopeEmailEnabled(Builder $query): Builder
    {
        return $query->where('email_enabled', true);
    }

    public function scopeSmsEnabled(Builder $query): Builder
    {
        return $query->where('sms_enabled', true);
    }

    public function scopeMarketingEnabled(Builder $query): Builder
    {
        return $query->where('marketing_enabled', true);
    }

    // Helpers

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'whatsapp' => $this->whatsapp_enabled,
            'push_notification' => $this->push_enabled,
            default => false,
        };
    }

    public function isCategoryEnabled(string $category): bool
    {
        return match ($category) {
            'marketing', 'promotional' => $this->marketing_enabled,
            'transactional' => $this->transactional_enabled,
            'reminder' => $this->reminder_enabled,
            default => true,
        };
    }

    public function canReceiveMessage(string $channel, string $category): bool
    {
        return $this->isSubscribed()
            && $this->isChannelEnabled($channel)
            && $this->isCategoryEnabled($category);
    }

    /**
     * Check if current time is within quiet hours.
     */
    public function isInQuietHours(): bool
    {
        $quietHours = $this->quiet_hours;

        if (empty($quietHours) || !isset($quietHours['start'], $quietHours['end'])) {
            return false;
        }

        $timezone = $this->timezone ?? 'UTC';
        $now = now($timezone);
        $start = $now->copy()->setTimeFromTimeString($quietHours['start']);
        $end = $now->copy()->setTimeFromTimeString($quietHours['end']);

        // Handle overnight quiet hours (e.g., 22:00 - 08:00)
        if ($start->gt($end)) {
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    public function unsubscribe(?string $reason = null): void
    {
        $this->update([
            'unsubscribed_at' => now(),
            'unsubscribe_reason' => $reason,
        ]);
    }

    public function resubscribe(): void
    {
        $this->update([
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ]);
    }
}
