<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IcReconciliationMatch extends Model
{
    use HasUuid;

    protected $table = 'ic_reconciliation_matches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'receivable_amount' => 'decimal:4',
            'payable_amount'    => 'decimal:4',
            'difference'        => 'decimal:4',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(IcReconciliationSession::class, 'session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IcReconciliationItem::class, 'match_id');
    }

    public function hasDifference(): bool
    {
        return abs((float) $this->difference) > 0.001;
    }
}
