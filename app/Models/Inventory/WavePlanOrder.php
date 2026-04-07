<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WavePlanOrder extends Model
{
    use HasFactory;

    public const ORDER_TYPE_SALES_ORDER      = 'sales_order';
    public const ORDER_TYPE_STOCK_TRANSFER   = 'stock_transfer';
    public const ORDER_TYPE_PURCHASE_RETURN  = 'purchase_return';

    protected $fillable = [
        'wave_plan_id',
        'order_type',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
        ];
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(WavePlan::class, 'wave_plan_id');
    }
}
