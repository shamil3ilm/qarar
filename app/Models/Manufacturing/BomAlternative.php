<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomAlternative extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_OBSOLETE = 'obsolete';

    public const USAGE_PRODUCTION = 'production';
    public const USAGE_ENGINEERING = 'engineering';
    public const USAGE_COSTING = 'costing';
    public const USAGE_PLANT_MAINTENANCE = 'plant_maintenance';

    protected $fillable = [
        'organization_id',
        'product_id',
        'alternative_number',
        'alternative_name',
        'bom_template_id',
        'valid_from',
        'valid_to',
        'is_default',
        'usage_type',
        'lot_size_from',
        'lot_size_to',
        'status',
        'notes',
    ];

    protected $casts = [
        'alternative_number' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_default' => 'boolean',
        'lot_size_from' => 'decimal:4',
        'lot_size_to' => 'decimal:4',
    ];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bomTemplate(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // Helpers

    public function isValidOn(string $date): bool
    {
        if ($this->valid_from->gt($date)) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->lt($date)) {
            return false;
        }

        return true;
    }

    public function isLotSizeCompatible(float $quantity): bool
    {
        if ($this->lot_size_from !== null && $quantity < (float) $this->lot_size_from) {
            return false;
        }

        if ($this->lot_size_to !== null && $quantity > (float) $this->lot_size_to) {
            return false;
        }

        return true;
    }
}
