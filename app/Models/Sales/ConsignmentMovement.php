<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentMovement extends Model
{
    public const TYPE_IN  = 'in';
    public const TYPE_OUT = 'out';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:4',
            'balance_after' => 'decimal:4',
            'moved_at'      => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(ConsignmentStock::class, 'consignment_stock_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ConsignmentOrder::class, 'order_id');
    }
}
