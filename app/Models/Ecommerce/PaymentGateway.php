<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PaymentGateway extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_PAYPAL = 'paypal';
    public const PROVIDER_TAP = 'tap';
    public const PROVIDER_MOYASAR = 'moyasar';
    public const PROVIDER_HYPERPAY = 'hyperpay';
    public const PROVIDER_MADA = 'mada';

    public const MODE_TEST = 'test';
    public const MODE_LIVE = 'live';

    protected $fillable = [
        'organization_id',
        'name',
        'provider',
        'credentials',
        'settings',
        'mode',
        'is_active',
        'is_default',
        'supported_currencies',
        'supported_methods',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supported_currencies' => 'array',
            'supported_methods' => 'array',
        ];
    }

    protected $hidden = ['credentials'];

    // Encrypt/decrypt credentials
    public function getCredentialsAttribute(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception) {
            return null;
        }
    }

    public function setCredentialsAttribute(?array $value): void
    {
        $this->attributes['credentials'] = $value === null
            ? null
            : Crypt::encryptString(json_encode($value));
    }

    // Relationships
    public function payments(): HasMany
    {
        return $this->hasMany(OnlinePayment::class, 'gateway_id');
    }

    // Business logic
    public function isLive(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }

    public function supportsCurrency(string $currencyCode): bool
    {
        if (empty($this->supported_currencies)) {
            return true;
        }

        return in_array($currencyCode, $this->supported_currencies, true);
    }

    public function supportsMethod(string $method): bool
    {
        if (empty($this->supported_methods)) {
            return true;
        }

        return in_array($method, $this->supported_methods, true);
    }

    public function setAsDefault(): void
    {
        self::where('organization_id', $this->organization_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeLive($query)
    {
        return $query->where('mode', self::MODE_LIVE);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
