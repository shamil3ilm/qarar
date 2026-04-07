<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MmParkedInvoice extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_PARKED = 'parked';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'invoice_number',
        'invoice_date',
        'posting_date',
        'total_amount',
        'currency',
        'purchase_order_id',
        'parked_by',
        'parked_at',
        'posted_by',
        'posted_at',
        'status',
        'line_items',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'posting_date' => 'date',
            'total_amount' => 'decimal:4',
            'parked_at' => 'datetime',
            'posted_at' => 'datetime',
            'line_items' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function parkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parked_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isParked(): bool
    {
        return $this->status === self::STATUS_PARKED;
    }
}
