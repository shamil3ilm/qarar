<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutlineAgreementRelease extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_OPEN          = 'open';
    public const STATUS_GOODS_RECEIVED = 'goods_received';
    public const STATUS_INVOICED      = 'invoiced';
    public const STATUS_CANCELLED     = 'cancelled';

    protected $fillable = [
        'organization_id',
        'outline_agreement_id',
        'outline_agreement_item_id',
        'purchase_order_id',
        'release_date',
        'release_quantity',
        'release_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'release_date'     => 'date',
            'release_quantity' => 'decimal:4',
            'release_value'    => 'decimal:4',
        ];
    }

    // Relationships

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(OutlineAgreement::class, 'outline_agreement_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OutlineAgreementItem::class, 'outline_agreement_item_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
