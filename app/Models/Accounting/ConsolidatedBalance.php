<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidatedBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'consolidation_period_id',
        'account_id',
        'entity_organization_id',
        'local_amount',
        'exchange_rate',
        'consolidated_amount',
    ];

    protected function casts(): array
    {
        return [
            'local_amount'         => 'decimal:4',
            'exchange_rate'        => 'decimal:6',
            'consolidated_amount'  => 'decimal:4',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ConsolidationPeriod::class, 'consolidation_period_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function entityOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'entity_organization_id');
    }
}
