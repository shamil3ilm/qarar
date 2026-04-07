<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterCapacity extends Model
{
    use HasUuid;

    protected $table = 'work_center_capacities';

    protected $fillable = [
        'organization_id',
        'work_center_id',
        'valid_from',
        'valid_to',
        'available_hours_per_day',
        'days_per_week',
        'efficiency_pct',
    ];

    protected $casts = [
        'valid_from'              => 'date',
        'valid_to'                => 'date',
        'available_hours_per_day' => 'decimal:2',
        'days_per_week'           => 'integer',
        'efficiency_pct'          => 'decimal:2',
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

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Return capacity records active on the given date.
     */
    public function scopeActiveOn($query, string $date)
    {
        return $query
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Compute the effective available hours for a single day, factoring in
     * days_per_week (scaled to a daily average) and efficiency_pct.
     *
     * Formula: available_hours_per_day × (days_per_week / 5) × (efficiency_pct / 100)
     */
    public function effectiveHoursPerDay(): float
    {
        $raw        = (float) $this->available_hours_per_day;
        $weekScale  = (float) $this->days_per_week / 5.0;
        $efficiency = (float) $this->efficiency_pct / 100.0;

        return round($raw * $weekScale * $efficiency, 2);
    }
}
