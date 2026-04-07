<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityLoad extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'work_center_id',
        'load_date',
        'planned_hours',
        'actual_hours',
        'available_hours',
    ];

    protected $casts = [
        'load_date'       => 'date',
        'planned_hours'   => 'decimal:2',
        'actual_hours'    => 'decimal:2',
        'available_hours' => 'decimal:2',
    ];

    // Relationships

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    // Helpers

    public function getUtilizationPercent(): float
    {
        $available = (float) $this->available_hours;

        if ($available <= 0.0) {
            return 0.0;
        }

        return round(((float) $this->planned_hours / $available) * 100, 2);
    }

    public function isOverloaded(): bool
    {
        return (float) $this->planned_hours > (float) $this->available_hours;
    }
}
