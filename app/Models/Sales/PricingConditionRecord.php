<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingConditionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'condition_type_id',
        'key_combination',
        'customer_id',
        'product_id',
        'price_list_id',
        'rate',
        'currency_code',
        'valid_from',
        'valid_to',
        'min_quantity',
        'max_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'min_quantity' => 'decimal:4',
            'max_quantity' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function conditionType(): BelongsTo
    {
        return $this->belongsTo(PricingConditionType::class, 'condition_type_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function isValidOn(\DateTimeInterface|string $date): bool
    {
        $check = \Carbon\Carbon::parse($date);

        if ($this->valid_from && $check->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && $check->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    public function appliesToQuantity(float $quantity): bool
    {
        if ($this->min_quantity !== null && $quantity < (float) $this->min_quantity) {
            return false;
        }

        if ($this->max_quantity !== null && $quantity > (float) $this->max_quantity) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidOn($query, string $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
        });
    }

    public function scopeForQuantity($query, float $quantity)
    {
        return $query->where(function ($q) use ($quantity) {
            $q->whereNull('min_quantity')->orWhere('min_quantity', '<=', $quantity);
        })->where(function ($q) use ($quantity) {
            $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity);
        });
    }
}
