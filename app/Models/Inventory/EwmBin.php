<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EwmBin extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'ewm_bins';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_BLOCKED  = 'blocked';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_RESERVED = 'reserved';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'max_weight_kg'      => 'float',
            'max_volume_m3'      => 'float',
            'current_weight_kg'  => 'float',
            'fill_pct'           => 'float',
            'mixed_products'     => 'boolean',
        ];
    }

    public function isAvailableForPutaway(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->fill_pct < 100;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function storageType(): BelongsTo
    {
        return $this->belongsTo(EwmStorageType::class, 'storage_type_id');
    }

    public function storageSection(): BelongsTo
    {
        return $this->belongsTo(EwmStorageSection::class, 'storage_section_id');
    }

    public function currentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'current_product_id');
    }

    public function inboundTransferOrders(): HasMany
    {
        return $this->hasMany(EwmTransferOrder::class, 'dest_bin_id');
    }

    public function outboundTransferOrders(): HasMany
    {
        return $this->hasMany(EwmTransferOrder::class, 'source_bin_id');
    }
}
