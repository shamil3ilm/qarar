<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class MessageCampaign extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    protected $table = 'messaging_automations';

    // Timing types
    public const TIMING_IMMEDIATE = 'immediate';
    public const TIMING_DELAYED = 'delayed';
    public const TIMING_SCHEDULED = 'scheduled';

    // Recipient types
    public const RECIPIENT_CONTACT = 'contact';
    public const RECIPIENT_USER = 'user';
    public const RECIPIENT_CUSTOM = 'custom';
    public const RECIPIENT_ROLE = 'role';

    // Delay units
    public const DELAY_MINUTES = 'minutes';
    public const DELAY_HOURS = 'hours';
    public const DELAY_DAYS = 'days';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'trigger_event',
        'trigger_entity',
        'timing',
        'delay_minutes',
        'delay_unit',
        'conditions',
        'channel_type',
        'template_id',
        'channel_id',
        'recipient_type',
        'recipient_config',
        'max_sends_per_contact',
        'rate_limit_period',
        'is_active',
        'execution_count',
        'last_executed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'recipient_config' => 'array',
            'delay_minutes' => 'integer',
            'max_sends_per_contact' => 'integer',
            'is_active' => 'boolean',
            'execution_count' => 'integer',
            'last_executed_at' => 'datetime',
        ];
    }

    // Relationships

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingConfiguration::class, 'channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class, 'automation_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForTriggerEvent(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    public function scopeForChannel(Builder $query, string $channelType): Builder
    {
        return $query->where('channel_type', $channelType);
    }

    public function scopeImmediate(Builder $query): Builder
    {
        return $query->where('timing', self::TIMING_IMMEDIATE);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isImmediate(): bool
    {
        return $this->timing === self::TIMING_IMMEDIATE;
    }

    public function isDelayed(): bool
    {
        return $this->timing === self::TIMING_DELAYED;
    }

    public function isScheduled(): bool
    {
        return $this->timing === self::TIMING_SCHEDULED;
    }

    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }

    public function getDelayInMinutes(): int
    {
        if (!$this->isDelayed()) {
            return 0;
        }

        return match ($this->delay_unit) {
            self::DELAY_HOURS => $this->delay_minutes * 60,
            self::DELAY_DAYS => $this->delay_minutes * 1440,
            default => $this->delay_minutes,
        };
    }

    public static function getTimingTypes(): array
    {
        return [self::TIMING_IMMEDIATE, self::TIMING_DELAYED, self::TIMING_SCHEDULED];
    }

    public static function getRecipientTypes(): array
    {
        return [self::RECIPIENT_CONTACT, self::RECIPIENT_USER, self::RECIPIENT_CUSTOM, self::RECIPIENT_ROLE];
    }
}
