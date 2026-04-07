<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverheadKeyRate extends Model
{
    use HasUuid;

    protected $fillable = [
        'overhead_key_id',
        'validity_from',
        'validity_to',
        'overhead_rate',
        'currency_code',
        'cost_center_id',
        'activity_type_id',
    ];

    protected function casts(): array
    {
        return [
            'validity_from'  => 'date',
            'validity_to'    => 'date',
            'overhead_rate'  => 'decimal:6',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function overheadKey(): BelongsTo
    {
        return $this->belongsTo(OverheadKey::class, 'overhead_key_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    /**
     * Check whether this rate is valid on a given date string (Y-m-d).
     */
    public function isValidOn(string $date): bool
    {
        $checkDate = \Carbon\Carbon::parse($date);

        if ($checkDate->lt($this->validity_from)) {
            return false;
        }

        if ($this->validity_to !== null && $checkDate->gt($this->validity_to)) {
            return false;
        }

        return true;
    }
}
