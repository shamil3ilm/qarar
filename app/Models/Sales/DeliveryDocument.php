<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryDocument extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_CREATED      = 'created';
    public const STATUS_PICKING      = 'picking';
    public const STATUS_PICKED       = 'picked';
    public const STATUS_PACKED       = 'packed';
    public const STATUS_GOODS_ISSUED = 'goods_issued';
    public const STATUS_CANCELLED    = 'cancelled';

    protected $fillable = [
        'organization_id',
        'delivery_number',
        'sales_order_id',
        'ship_to_contact_id',
        'warehouse_id',
        'planned_goods_issue_date',
        'actual_goods_issue_date',
        'delivery_date',
        'carrier',
        'tracking_number',
        'status',
        'weight_gross',
        'weight_net',
        'volume',
    ];

    protected $casts = [
        'planned_goods_issue_date' => 'date',
        'actual_goods_issue_date'  => 'date',
        'delivery_date'            => 'date',
        'weight_gross'             => 'decimal:3',
        'weight_net'               => 'decimal:3',
        'volume'                   => 'decimal:3',
    ];

    // Relations

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function shipToContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'ship_to_contact_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryDocumentLine::class);
    }

    public function pickDocuments(): HasMany
    {
        return $this->hasMany(PickDocument::class);
    }
}
