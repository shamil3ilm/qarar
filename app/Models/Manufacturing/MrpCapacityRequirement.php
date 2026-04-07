<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrpCapacityRequirement extends Model
{
    use HasUuid;

    public const STATUS_FEASIBLE   = 'feasible';
    public const STATUS_OVERLOADED = 'overloaded';

    protected $table = 'mrp_capacity_requirements';

    protected $fillable = [
        'organization_id',
        'mrp_run_id',
        'work_center_id',
        'planned_order_id',
        'required_date',
        'required_hours',
        'available_hours',
        'load_pct',
        'status',
    ];

    protected $casts = [
        'required_date'   => 'date',
        'required_hours'  => 'decimal:2',
        'available_hours' => 'decimal:2',
        'load_pct'        => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOverloaded(): bool
    {
        return $this->status === self::STATUS_OVERLOADED;
    }
}
