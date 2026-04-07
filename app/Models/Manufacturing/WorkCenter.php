<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_MACHINE = 'machine';
    public const TYPE_LABOR = 'labor';
    public const TYPE_ASSEMBLY = 'assembly';
    public const TYPE_INSPECTION = 'inspection';
    public const TYPE_OTHER = 'other';

    public const CALENDAR_5DAY = '5day';
    public const CALENDAR_6DAY = '6day';
    public const CALENDAR_7DAY = '7day';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'work_center_type',
        'capacity_per_day',
        'efficiency_percent',
        'calendar_type',
        'cost_per_hour',
        'currency_code',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'capacity_per_day'   => 'decimal:2',
        'efficiency_percent' => 'decimal:2',
        'cost_per_hour'      => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(WorkCenterException::class)->orderBy('exception_date');
    }

    public function capacityRequirements(): HasMany
    {
        return $this->hasMany(CapacityRequirement::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(CapacityLoad::class)->orderBy('load_date');
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('work_center_type', $type);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Get the effective available hours for a specific date,
     * applying exception overrides and then efficiency.
     */
    public function getAvailableHoursForDate(\DateTime $date): float
    {
        $dateStr = $date->format('Y-m-d');

        // Check if there is an exception entry for this date
        $exception = $this->exceptions()
            ->where('exception_date', $dateStr)
            ->first();

        if ($exception !== null) {
            return (float) $exception->available_hours;
        }

        $dayOfWeek = (int) $date->format('N'); // 1=Mon … 7=Sun

        // Determine whether this day is a working day under the calendar
        $isWorkingDay = match ($this->calendar_type) {
            self::CALENDAR_7DAY => true,
            self::CALENDAR_6DAY => $dayOfWeek <= 6,  // Mon-Sat
            default             => $dayOfWeek <= 5,  // Mon-Fri (5day)
        };

        if (!$isWorkingDay) {
            return 0.0;
        }

        // Apply efficiency factor
        $rawCapacity = (float) $this->capacity_per_day;
        $efficiency  = (float) $this->efficiency_percent / 100;

        return round($rawCapacity * $efficiency, 2);
    }

    /**
     * Get utilization percentage for a given date string (Y-m-d).
     */
    public function getUtilizationPercent(string $date): float
    {
        $load = $this->loads()->where('load_date', $date)->first();

        if ($load === null) {
            return 0.0;
        }

        $available = (float) $load->available_hours;

        if ($available <= 0.0) {
            return 0.0;
        }

        return round(((float) $load->planned_hours / $available) * 100, 2);
    }

    /**
     * Whether this work center is a working day on a given date.
     */
    public function isWorkingDay(\DateTime $date): bool
    {
        return $this->getAvailableHoursForDate($date) > 0.0;
    }
}
