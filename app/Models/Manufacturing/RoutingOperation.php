<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'routing_id',
        'sequence_number',
        'operation_code',
        'description',
        'work_center_id',
        'setup_time',
        'machine_time',
        'labor_time',
        'inter_operation_time',
        'control_key',
    ];

    protected $casts = [
        'sequence_number'      => 'integer',
        'setup_time'           => 'decimal:4',
        'machine_time'         => 'decimal:4',
        'labor_time'           => 'decimal:4',
        'inter_operation_time' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function routing(): BelongsTo
    {
        return $this->belongsTo(RoutingHeader::class, 'routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Total operation time for a given quantity (setup once + run time per unit).
     */
    public function getTotalTime(float $quantity): float
    {
        $setup = (float) $this->setup_time;
        $run   = ((float) $this->machine_time + (float) $this->labor_time) * $quantity;

        return round($setup + $run, 4);
    }

    /**
     * Cost for this operation for a given quantity.
     */
    public function getCost(float $quantity): float
    {
        $costPerHour = (float) ($this->workCenter?->cost_per_hour ?? 0);

        return round($this->getTotalTime($quantity) * $costPerHour, 4);
    }
}
