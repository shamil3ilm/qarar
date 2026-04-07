<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentToleranceGroup extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const APPLIES_CUSTOMER = 'customer';
    public const APPLIES_SUPPLIER = 'supplier';
    public const APPLIES_BOTH     = 'both';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(PaymentToleranceItem::class, 'tolerance_group_id');
    }

    public function differencePosts(): HasMany
    {
        return $this->hasMany(PaymentDifferencePost::class, 'tolerance_group_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find the tolerance item for a given currency, or null if not configured.
     */
    public function itemForCurrency(string $currencyCode): ?PaymentToleranceItem
    {
        return $this->items()->where('currency_code', $currencyCode)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
