<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EwmStorageType extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'ewm_storage_types';

    public const TYPE_BULK         = 'bulk';
    public const TYPE_SHELVING      = 'shelving';
    public const TYPE_HIGH_BAY      = 'high_bay';
    public const TYPE_PALLET        = 'pallet';
    public const TYPE_FREEZER       = 'freezer';
    public const TYPE_HAZMAT        = 'hazmat';
    public const TYPE_OPEN_STORAGE  = 'open_storage';

    public const STRATEGY_FIFO        = 'fifo';
    public const STRATEGY_FEFO        = 'fefo';
    public const STRATEGY_LIFO        = 'lifo';
    public const STRATEGY_NEAREST_BIN = 'nearest_bin';
    public const STRATEGY_FIXED_BIN   = 'fixed_bin';
    public const STRATEGY_MAX_FILL    = 'max_fill';
    public const STRATEGY_OPEN        = 'open_storage';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allow_partial_putaway' => 'boolean',
            'mixed_storage'         => 'boolean',
            'is_active'             => 'boolean',
            'max_weight_kg'         => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function storageSections(): HasMany
    {
        return $this->hasMany(EwmStorageSection::class, 'storage_type_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(EwmBin::class, 'storage_type_id');
    }

    public function putawayRules(): HasMany
    {
        return $this->hasMany(EwmPutawayRule::class, 'storage_type_id');
    }
}
