<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceChannel extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $guarded = ['id'];

    // Status values
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DISCONNECTED = 'disconnected';

    // Platform values
    public const PLATFORM_SHOPIFY = 'shopify';
    public const PLATFORM_WOOCOMMERCE = 'woocommerce';
    public const PLATFORM_MAGENTO = 'magento';
    public const PLATFORM_CUSTOM = 'custom';
    public const PLATFORM_MARKETPLACE = 'marketplace';

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'settings' => 'array',
            'sync_products' => 'boolean',
            'sync_orders' => 'boolean',
            'sync_inventory' => 'boolean',
            'auto_fulfill' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    // Relationships

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function defaultCustomer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'default_customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class, 'channel_id');
    }

    public function productMappings(): HasMany
    {
        return $this->hasMany(EcommerceProductMapping::class, 'channel_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(EcommerceSyncLog::class, 'channel_id');
    }

    // Scopes

    public function scopeByPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isDisconnected(): bool
    {
        return $this->status === self::STATUS_DISCONNECTED;
    }
}
