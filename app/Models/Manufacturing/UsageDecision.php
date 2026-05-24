<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageDecision extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'usage_decisions';

    protected $guarded = ['id'];

    // SAP decision codes
    public const DECISION_ACCEPT  = 'accept';
    public const DECISION_REJECT  = 'reject';
    public const DECISION_PARTIAL = 'partial';

    // SAP QM movement type references
    public const MOVEMENT_UNRESTRICTED = '321'; // quality inspection → unrestricted-use stock
    public const MOVEMENT_BLOCKED      = '346'; // quality inspection → blocked stock
    public const MOVEMENT_SCRAP        = '551'; // scrap from quality inspection

    protected function casts(): array
    {
        return [
            'qty_unrestricted' => 'decimal:4',
            'qty_blocked'      => 'decimal:4',
            'qty_scrap'        => 'decimal:4',
            'decided_at'       => 'datetime',
        ];
    }

    public function inspectionLot(): BelongsTo
    {
        return $this->belongsTo(InspectionLot::class, 'inspection_lot_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Total quantity accounted for by this decision.
     */
    public function totalQuantity(): float
    {
        return (float) bcadd(
            bcadd((string) $this->qty_unrestricted, (string) $this->qty_blocked, 4),
            (string) $this->qty_scrap,
            4
        );
    }
}
