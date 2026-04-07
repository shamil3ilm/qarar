<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ChannelTemplateApproval extends Model
{
    use HasFactory;
    protected $table = 'channel_template_approvals';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'template_id',
        'channel_type',
        'provider_template_id',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForChannel(Builder $query, string $channelType): Builder
    {
        return $query->where('channel_type', $channelType);
    }

    // Helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function approve(?string $providerTemplateId = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'provider_template_id' => $providerTemplateId,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }
}
