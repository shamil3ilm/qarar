<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\CostCenter;
use App\Models\Accounting\InternalOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePoLine extends Model
{
    protected $fillable = [
        'service_purchase_order_id',
        'line_number',
        'service_description',
        'service_number',
        'quantity',
        'uom',
        'unit_price',
        'total_price',
        'cost_center_id',
        'internal_order_id',
        'accepted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
        ];
    }

    public function servicePurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ServicePurchaseOrder::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function internalOrder(): BelongsTo
    {
        return $this->belongsTo(InternalOrder::class);
    }

    public function entrySheetLines(): HasMany
    {
        return $this->hasMany(ServiceEntrySheetLine::class, 'service_po_line_id');
    }

    public function getRemainingQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) $this->accepted_quantity, 4);
    }
}
