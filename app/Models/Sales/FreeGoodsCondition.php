<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreeGoodsCondition extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_INCLUSIVE = 'inclusive';
    public const TYPE_EXCLUSIVE = 'exclusive';

    public const CALC_QUANTITY   = 'quantity';
    public const CALC_PERCENTAGE = 'percentage';

    protected $fillable = [
        'organization_id',
        'condition_number',
        'customer_id',
        'customer_group_id',
        'product_id',
        'free_product_id',
        'free_goods_type',
        'minimum_quantity',
        'free_quantity',
        'calculation_type',
        'valid_from',
        'valid_to',
        'active',
    ];

    protected $casts = [
        'valid_from'       => 'date',
        'valid_to'         => 'date',
        'minimum_quantity' => 'decimal:4',
        'free_quantity'    => 'decimal:4',
        'active'           => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function freeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    /**
     * Scope conditions applicable to an order line for a customer.
     */
    public function scopeApplicable($query, int $productId, ?int $customerId, ?int $customerGroupId): void
    {
        $today = now()->toDateString();
        $query->where('product_id', $productId)
              ->where('active', true)
              ->where('valid_from', '<=', $today)
              ->where(function ($q) use ($today): void {
                  $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
              })
              ->where(function ($q) use ($customerId, $customerGroupId): void {
                  $q->whereNull('customer_id')
                    ->orWhere('customer_id', $customerId)
                    ->orWhere('customer_group_id', $customerGroupId);
              });
    }
}
