<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferPrice extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const METHOD_STANDARD_COST = 'standard_cost';
    public const METHOD_MARKET_PRICE  = 'market_price';
    public const METHOD_COST_PLUS     = 'cost_plus';
    public const METHOD_NEGOTIATED    = 'negotiated';

    public const METHODS = [
        self::METHOD_STANDARD_COST,
        self::METHOD_MARKET_PRICE,
        self::METHOD_COST_PLUS,
        self::METHOD_NEGOTIATED,
    ];

    protected $fillable = [
        'organization_id',
        'from_profit_center_id',
        'to_profit_center_id',
        'from_cost_center_id',
        'to_cost_center_id',
        'product_id',
        'cost_element_id',
        'transfer_price_method',
        'base_price',
        'markup_percentage',
        'effective_from',
        'effective_to',
        'currency_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price'         => 'decimal:4',
            'markup_percentage'  => 'decimal:4',
            'effective_from'     => 'date',
            'effective_to'       => 'date',
            'is_active'          => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function fromProfitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'from_profit_center_id');
    }

    public function toProfitCenter(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'to_profit_center_id');
    }

    public function fromCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'from_cost_center_id');
    }

    public function toCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'to_cost_center_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(TransferPriceCondition::class, 'transfer_price_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TransferPriceHistory::class, 'transfer_price_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    /**
     * Calculate the effective transfer price including markup.
     */
    public function getEffectivePrice(): float
    {
        $base   = (float) $this->base_price;
        $markup = (float) $this->markup_percentage;

        return $base * (1 + $markup / 100);
    }
}
