<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpqOption extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'cpq_options';

    public const MODIFIER_FIXED      = 'fixed';
    public const MODIFIER_PERCENTAGE = 'percentage';
    public const MODIFIER_NONE       = 'none';

    protected $fillable = [
        'cpq_option_group_id',
        'option_code',
        'name',
        'description',
        'price_modifier_type',
        'price_modifier_value',
        'is_default',
        'is_active',
        'sort_order',
        'linked_product_id',
    ];

    protected function casts(): array
    {
        return [
            'price_modifier_value' => 'decimal:4',
            'is_default'           => 'boolean',
            'is_active'            => 'boolean',
            'sort_order'           => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CpqOptionGroup::class, 'cpq_option_group_id');
    }

    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'linked_product_id');
    }

    /**
     * Calculate the price modifier effect on a given base price.
     */
    public function applyModifier(float $basePrice): float
    {
        return match ($this->price_modifier_type) {
            self::MODIFIER_FIXED      => (float) $this->price_modifier_value,
            self::MODIFIER_PERCENTAGE => $basePrice * ((float) $this->price_modifier_value / 100),
            default                   => 0.0,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
