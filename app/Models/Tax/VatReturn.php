<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a GCC VAT return filing period stored in vat_return_periods
 * (migration 2026_03_25_000002).
 *
 * This model is distinct from the legacy VatReturnPeriod model, which
 * uses the same table but references a different column set from the
 * earlier schema (boxes, submitted_at, reference_number, etc.).
 * Both models map to vat_return_periods; new code should use VatReturn.
 */
class VatReturn extends Model
{
    use HasUuid;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'vat_return_periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start'    => 'date',
            'period_end'      => 'date',
            'due_date'        => 'date',
            'total_sales'     => 'decimal:2',
            'total_purchases' => 'decimal:2',
            'output_vat'      => 'decimal:2',
            'input_vat'       => 'decimal:2',
            'net_vat_payable' => 'decimal:2',
            'filed_at'        => 'datetime',
        ];
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(VatReturnLineItem::class, 'vat_return_period_id');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFiled(): bool
    {
        return in_array($this->status, ['filed', 'paid'], true);
    }
}
