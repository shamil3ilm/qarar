<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $table = 'message_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'attachments_config' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    // Channel types
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_PUSH_NOTIFICATION = 'push_notification';

    // Categories
    public const CATEGORY_TRANSACTIONAL = 'transactional';
    public const CATEGORY_PROMOTIONAL = 'promotional';
    public const CATEGORY_REMINDER = 'reminder';
    public const CATEGORY_NOTIFICATION = 'notification';

    // Relationships

    public function parentTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'parent_template_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'parent_template_id');
    }

    public function channelApprovals(): HasMany
    {
        return $this->hasMany(ChannelTemplateApproval::class, 'template_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function scopeForChannel(Builder $query, string $channelType): Builder
    {
        return $query->where('channel_type', $channelType);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('subject', 'like', "%{$search}%");
        });
    }

    // Helpers

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function getAvailableVariables(): array
    {
        return $this->variables ?? [];
    }

    public function render(array $data): array
    {
        $subject = $this->subject ?? '';
        $body = $this->body ?? '';

        foreach ($data as $key => $value) {
            $subject = str_replace("{{$key}}", (string) $value, $subject);
            $body = str_replace("{{$key}}", (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
