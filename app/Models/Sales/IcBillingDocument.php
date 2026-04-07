<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IcBillingDocument extends Model
{
    use HasUuid, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'intercompany_sales_order_id',
        'selling_organization_id',
        'buying_organization_id',
        'document_number',
        'billing_date',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'journal_entry_id',
        'posted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_date' => 'date',
            'subtotal'     => 'decimal:4',
            'tax_amount'   => 'decimal:4',
            'total_amount' => 'decimal:4',
            'status'       => 'string',
            'posted_at'    => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function intercompanySalesOrder(): BelongsTo
    {
        return $this->belongsTo(IntercompanySalesOrder::class, 'intercompany_sales_order_id');
    }

    public function sellingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'selling_organization_id');
    }

    public function buyingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buying_organization_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
