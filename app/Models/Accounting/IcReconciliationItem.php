<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcReconciliationItem extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'ic_reconciliation_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:4',
            'transaction_date' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(IcReconciliationSession::class, 'session_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(IcReconciliationMatch::class, 'match_id');
    }
}
