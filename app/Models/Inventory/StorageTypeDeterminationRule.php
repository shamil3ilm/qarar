<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageTypeDeterminationRule extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    public const MOVEMENT_GOODS_RECEIPT = 'goods_receipt';
    public const MOVEMENT_GOODS_ISSUE   = 'goods_issue';
    public const MOVEMENT_TRANSFER      = 'transfer';
    public const MOVEMENT_RETURNS       = 'returns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'max_weight_kg' => 'decimal:2',
            'priority'      => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function storageType(): BelongsTo
    {
        return $this->belongsTo(StorageType::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForMovement(Builder $query, string $movementType): Builder
    {
        return $query->where('movement_type', $movementType);
    }
}
