<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrderSettlement extends Model
{
    use BelongsToOrganization, HasUuid;

    public const RULE_FULL    = 'full';
    public const RULE_PARTIAL = 'partial';

    public const RECEIVER_COST_CENTER = 'cost_center';
    public const RECEIVER_ASSET       = 'asset';
    public const RECEIVER_ORDER       = 'order';
    public const RECEIVER_WBS         = 'wbs';

    protected $fillable = [
        'organization_id',
        'maintenance_order_id',
        'settlement_rule_type',
        'receiver_type',
        'receiver_id',
        'percentage',
        'settled_amount',
        'settlement_date',
        'fiscal_year',
        'period',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage'      => 'decimal:2',
            'settled_amount'  => 'decimal:4',
            'settlement_date' => 'date',
            'fiscal_year'     => 'integer',
            'period'          => 'integer',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
