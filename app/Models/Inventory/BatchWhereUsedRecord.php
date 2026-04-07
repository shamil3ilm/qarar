<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchWhereUsedRecord extends Model
{
    use BelongsToOrganization, HasUuid;

    public const USAGE_WORK_ORDER    = 'work_order';
    public const USAGE_PROCESS_ORDER = 'process_order';
    public const USAGE_SALES_INVOICE = 'sales_invoice';
    public const USAGE_STOCK_TRANSFER = 'stock_transfer';
    public const USAGE_ADJUSTMENT    = 'adjustment';

    protected $fillable = [
        'organization_id',
        'inventory_batch_id',
        'usage_type',
        'reference_id',
        'reference_number',
        'product_id',
        'quantity_used',
        'used_at',
        'warehouse_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_used' => 'decimal:4',
            'used_at'       => 'datetime',
        ];
    }

    // Relationships

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
