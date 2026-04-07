<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a TDS deduction entry stored in tds_deductions
 * (migration 2026_03_25_000002).
 *
 * This model is distinct from the legacy TdsDeduction model, which maps
 * to the same table but uses a different column set (payment_date,
 * payment_amount, net_tds, period_quarter, etc.) from the prior schema.
 * New code should use TdsEntry for the 2026 schema.
 */
class TdsEntry extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $table = 'tds_deductions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date'   => 'date',
            'transaction_amount' => 'decimal:2',
            'tds_rate'           => 'decimal:2',
            'tds_amount'         => 'decimal:2',
            'deposited_at'       => 'datetime',
        ];
    }

    public function tdsSection(): BelongsTo
    {
        return $this->belongsTo(TdsConfiguration::class, 'tds_section_id');
    }

    public function isDeposited(): bool
    {
        return $this->status === 'deposited';
    }
}
