<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorCreditNote extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'organization_id',
        'credit_note_number',
        'vendor_id',
        'bill_id',
        'issue_date',
        'credit_date',
        'status',
        'reason',
        'subtotal',
        'tax_amount',
        'total_amount',
        'applied_amount',
        'notes',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'credit_date' => 'date',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'applied_amount' => 'decimal:4',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VendorCreditNoteLine::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function getRemainingAmount(): float
    {
        return (float) bcsub((string) $this->total_amount, (string) $this->applied_amount, 4);
    }
}
