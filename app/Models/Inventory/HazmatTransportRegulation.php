<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HazmatTransportRegulation extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    public const MODE_ROAD = 'road';
    public const MODE_AIR  = 'air';
    public const MODE_SEA  = 'sea';
    public const MODE_RAIL = 'rail';

    protected $table = 'hazmat_transport_regulations';

    protected $fillable = [
        'organization_id',
        'product_id',
        'un_number',
        'proper_shipping_name',
        'hazard_class',
        'packing_group',
        'transport_mode',
        'is_forbidden',
        'special_provisions',
    ];

    protected function casts(): array
    {
        return [
            'is_forbidden' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForMode(Builder $query, string $mode): Builder
    {
        return $query->where('transport_mode', $mode);
    }

    public function scopeForbidden(Builder $query): Builder
    {
        return $query->where('is_forbidden', true);
    }
}
