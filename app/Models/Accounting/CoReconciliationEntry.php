<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoReconciliationEntry extends Model
{
    use BelongsToOrganization;
    use HasUuid;

    protected $table = 'co_reconciliation_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CoReconciliationRun::class, 'reconciliation_run_id');
    }

    public function senderCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'sender_cost_center_id');
    }

    public function receiverCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'receiver_cost_center_id');
    }

    public function costElement(): BelongsTo
    {
        return $this->belongsTo(CostElement::class, 'cost_element_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
