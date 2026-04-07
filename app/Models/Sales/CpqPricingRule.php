<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpqPricingRule extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'cpq_pricing_rules';

    public const DISCOUNT_PERCENTAGE     = 'percentage';
    public const DISCOUNT_FIXED          = 'fixed';
    public const DISCOUNT_PRICE_OVERRIDE = 'price_override';

    protected $fillable = [
        'cpq_configurable_product_id',
        'rule_name',
        'condition_json',
        'discount_type',
        'discount_value',
        'priority',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'discount_value' => 'decimal:4',
            'priority'       => 'integer',
            'valid_from'     => 'date',
            'valid_to'       => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function configurableProduct(): BelongsTo
    {
        return $this->belongsTo(CpqConfigurableProduct::class, 'cpq_configurable_product_id');
    }

    public function isCurrentlyValid(): bool
    {
        $now = now()->toDateString();

        if ($this->valid_from && $this->valid_from->toDateString() > $now) {
            return false;
        }

        if ($this->valid_to && $this->valid_to->toDateString() < $now) {
            return false;
        }

        return $this->is_active;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
