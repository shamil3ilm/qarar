<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Core\Organization;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcPurchaseOrderLink extends Model
{
    protected $fillable = [
        'intercompany_sales_order_id',
        'purchase_order_id',
        'buying_organization_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function intercompanySalesOrder(): BelongsTo
    {
        return $this->belongsTo(IntercompanySalesOrder::class, 'intercompany_sales_order_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function buyingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buying_organization_id');
    }
}
