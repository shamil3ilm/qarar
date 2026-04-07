<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WipValuation extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'valuation_date' => 'date',
        'completed_qty'  => 'decimal:4',
        'wip_qty'        => 'decimal:4',
        'material_wip'   => 'decimal:4',
        'labor_wip'      => 'decimal:4',
        'overhead_wip'   => 'decimal:4',
        'total_wip'      => 'decimal:4',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // Helpers

    public function getTotalWip(): float
    {
        return (float) bcadd(
            bcadd((string) $this->material_wip, (string) $this->labor_wip, 4),
            (string) $this->overhead_wip,
            4
        );
    }

    public function getCompletionPct(): float
    {
        $total = (float) $this->completed_qty + (float) $this->wip_qty;
        if ($total <= 0) {
            return 0.0;
        }

        return round(((float) $this->completed_qty / $total) * 100, 2);
    }
}
