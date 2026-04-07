<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WithholdingTaxCode extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const APPLICABLE_SUPPLIER  = 'supplier';
    public const APPLICABLE_CUSTOMER  = 'customer';
    public const APPLICABLE_BOTH      = 'both';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate'             => 'decimal:4',
            'threshold_amount' => 'decimal:4',
            'ceiling_amount'   => 'decimal:4',
            'is_active'        => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WithholdingTaxLine::class, 'wht_code_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Compute WHT amount for a given gross. */
    public function compute(float $grossAmount): float
    {
        return round($grossAmount * ((float) $this->rate / 100), 4);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSupplier($query)
    {
        return $query->whereIn('applicable_to', [self::APPLICABLE_SUPPLIER, self::APPLICABLE_BOTH]);
    }

    public function scopeForCustomer($query)
    {
        return $query->whereIn('applicable_to', [self::APPLICABLE_CUSTOMER, self::APPLICABLE_BOTH]);
    }
}
