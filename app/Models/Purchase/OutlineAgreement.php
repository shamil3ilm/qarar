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

class OutlineAgreement extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_QUANTITY_CONTRACT    = 'quantity_contract';
    public const TYPE_VALUE_CONTRACT       = 'value_contract';
    public const TYPE_SCHEDULING_AGREEMENT = 'scheduling_agreement';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'agreement_number',
        'agreement_type',
        'status',
        'valid_from',
        'valid_to',
        'currency_code',
        'target_quantity',
        'target_value',
        'released_quantity',
        'released_value',
        'payment_terms',
        'delivery_days',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'         => 'date',
            'valid_to'           => 'date',
            'target_quantity'    => 'decimal:4',
            'target_value'       => 'decimal:4',
            'released_quantity'  => 'decimal:4',
            'released_value'     => 'decimal:4',
        ];
    }

    // Relationships

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutlineAgreementItem::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(OutlineAgreementRelease::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    // Helpers

    public function getRemainingQuantity(): ?string
    {
        if ($this->target_quantity === null) {
            return null;
        }

        return bcsub((string) $this->target_quantity, (string) $this->released_quantity, 4);
    }

    public function getRemainingValue(): ?string
    {
        if ($this->target_value === null) {
            return null;
        }

        return bcsub((string) $this->target_value, (string) $this->released_value, 4);
    }

    public function isExpired(): bool
    {
        return $this->valid_to !== null && $this->valid_to->isPast();
    }
}
