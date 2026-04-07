<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CpqConfigurableProduct extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    protected $table = 'cpq_configurable_products';

    protected $fillable = [
        'organization_id',
        'product_id',
        'name',
        'description',
        'base_price',
        'currency_code',
        'configuration_validity_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price'                  => 'decimal:4',
            'configuration_validity_days' => 'integer',
            'is_active'                   => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(CpqOptionGroup::class)->orderBy('sort_order');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CpqPricingRule::class)->orderBy('priority');
    }

    public function constraintRules(): HasMany
    {
        return $this->hasMany(CpqConstraintRule::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(CpqConfiguration::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
