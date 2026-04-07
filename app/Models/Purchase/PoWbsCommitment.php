<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Projects\WbsElement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoWbsCommitment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_DELIVERED = 'partially_delivered';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'purchase_order_line_id',
        'wbs_element_id',
        'committed_amount',
        'currency_code',
        'commitment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'committed_amount' => 'decimal:4',
            'commitment_date'  => 'date',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPartiallyDelivered(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_DELIVERED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForWbs($query, int $wbsElementId)
    {
        return $query->where('wbs_element_id', $wbsElementId);
    }
}
