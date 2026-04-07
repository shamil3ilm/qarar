<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialLedgerMappingRule extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'special_ledger_id',
        'source_account_id',
        'account_type',
        'target_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function specialLedger(): BelongsTo
    {
        return $this->belongsTo(SpecialLedger::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }
}
