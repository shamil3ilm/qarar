<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundMessage extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id'];

    // Status values
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'cost' => 'decimal:4',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    // Relationships

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessagingConfiguration::class, 'channel_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(MessageCampaign::class, 'automation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OutboundMessageAttachment::class, 'message_id');
    }

    // Scopes

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeSending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeBounced(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BOUNCED);
    }

    // State transition methods

    public function markAsSending(): void
    {
        $this->update(['status' => self::STATUS_SENDING]);
    }

    public function markAsSent(?string $providerMessageId = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason, ?array $providerResponse = null): void
    {
        $update = [
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ];

        if ($providerResponse !== null) {
            $update['provider_response'] = $providerResponse;
        }

        $this->update($update);
    }

    public function markAsBounced(): void
    {
        $this->update([
            'status' => self::STATUS_BOUNCED,
            'bounced_at' => now(),
        ]);
    }

    public function markAsOpened(): void
    {
        $this->update([
            'status' => self::STATUS_OPENED,
            'opened_at' => now(),
        ]);
    }

    public function markAsClicked(): void
    {
        $this->update([
            'status' => self::STATUS_CLICKED,
            'clicked_at' => now(),
        ]);
    }

    // Helpers

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
