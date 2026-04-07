<?php

declare(strict_types=1);

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class MessagingConfiguration extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'messaging_channels';

    // Channel types
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_PUSH = 'push_notification';

    // Providers
    public const PROVIDER_SMTP = 'smtp';
    public const PROVIDER_SENDGRID = 'sendgrid';
    public const PROVIDER_TWILIO = 'twilio';
    public const PROVIDER_VONAGE = 'vonage';
    public const PROVIDER_FIREBASE = 'firebase';
    public const PROVIDER_WHATSAPP_BUSINESS = 'whatsapp_business';

    protected $fillable = [
        'organization_id',
        'channel_type',
        'name',
        'provider',
        'credentials',
        'settings',
        'sender_name',
        'sender_address',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel(Builder $query, string $channelType): Builder
    {
        return $query->where('channel_type', $channelType);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Get the default channel configuration for a given channel type.
     */
    public static function getDefault(string $channelType): ?static
    {
        return static::active()
            ->forChannel($channelType)
            ->default()
            ->first();
    }

    /**
     * Get rate limit settings.
     */
    public function getRateLimit(): ?array
    {
        return $this->settings['rate_limit'] ?? null;
    }

    /**
     * Get sender info.
     */
    public function getSenderInfo(): array
    {
        return [
            'name' => $this->sender_name,
            'address' => $this->sender_address,
        ];
    }

    public static function getChannelTypes(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_SMS,
            self::CHANNEL_WHATSAPP,
            self::CHANNEL_PUSH,
        ];
    }

    public static function getProviders(): array
    {
        return [
            self::PROVIDER_SMTP,
            self::PROVIDER_SENDGRID,
            self::PROVIDER_TWILIO,
            self::PROVIDER_VONAGE,
            self::PROVIDER_FIREBASE,
            self::PROVIDER_WHATSAPP_BUSINESS,
        ];
    }
}
