<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents an e-way bill stored in the eway_bills table
 * (migration 2026_03_25_000002).
 *
 * Distinct from the legacy Ewaybill model, which maps to the ewaybills
 * table with a different column set (gstin_supplier, gstin_recipient,
 * valid_until, etc.) from the prior schema.
 */
class EwayBillRecord extends Model
{
    use HasUuid;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'eway_bills';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_upto'   => 'datetime',
            'generated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->valid_upto === null || $this->valid_upto->isFuture());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->valid_upto !== null && $this->valid_upto->isPast());
    }
}
