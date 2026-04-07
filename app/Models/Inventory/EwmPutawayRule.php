<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EwmPutawayRule extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'ewm_putaway_rules';

    public const STRATEGY_FIFO        = 'fifo';
    public const STRATEGY_FEFO        = 'fefo';
    public const STRATEGY_LIFO        = 'lifo';
    public const STRATEGY_NEAREST_BIN = 'nearest_bin';
    public const STRATEGY_FIXED_BIN   = 'fixed_bin';
    public const STRATEGY_MAX_FILL    = 'max_fill';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'priority'  => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function storageType(): BelongsTo
    {
        return $this->belongsTo(EwmStorageType::class, 'storage_type_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
