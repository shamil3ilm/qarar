<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorConsignmentReceipt extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'vendor_consignment_receipts';

    protected $fillable = [
        'organization_id',
        'vendor_consignment_stock_id',
        'purchase_order_id',
        'receipt_date',
        'quantity_received',
        'unit_id',
        'vendor_delivery_note',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date'      => 'date',
            'quantity_received' => 'decimal:4',
        ];
    }

    public function consignmentStock(): BelongsTo
    {
        return $this->belongsTo(VendorConsignmentStock::class, 'vendor_consignment_stock_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
