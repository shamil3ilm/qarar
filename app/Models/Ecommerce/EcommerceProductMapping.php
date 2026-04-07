<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class EcommerceProductMapping extends Model
{
    use HasFactory;
    protected $fillable = [
        'channel_id',
        'product_id',
        'external_product_id',
        'external_variant_id',
        'external_sku',
        'sync_enabled',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    // Relationships
    public function channel(): BelongsTo
    {
        return $this->belongsTo(EcommerceChannel::class, 'channel_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    public function scopeByChannel($query, int $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByExternalProduct($query, string $externalProductId)
    {
        return $query->where('external_product_id', $externalProductId);
    }
}
