<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;

    // Maintenance type constants
    public const TYPE_PREVENTIVE     = 'preventive';
    public const TYPE_PREDICTIVE     = 'predictive';
    public const TYPE_CONDITION_BASED = 'condition_based';

    public const MAINTENANCE_TYPES = [
        self::TYPE_PREVENTIVE,
        self::TYPE_PREDICTIVE,
        self::TYPE_CONDITION_BASED,
    ];

    // Frequency type constants
    public const FREQ_DAILY       = 'daily';
    public const FREQ_WEEKLY      = 'weekly';
    public const FREQ_MONTHLY     = 'monthly';
    public const FREQ_QUARTERLY   = 'quarterly';
    public const FREQ_YEARLY      = 'yearly';
    public const FREQ_HOURS       = 'hours';
    public const FREQ_KILOMETERS  = 'kilometers';

    public const FREQUENCY_TYPES = [
        self::FREQ_DAILY,
        self::FREQ_WEEKLY,
        self::FREQ_MONTHLY,
        self::FREQ_QUARTERLY,
        self::FREQ_YEARLY,
        self::FREQ_HOURS,
        self::FREQ_KILOMETERS,
    ];

    protected $fillable = [
        'organization_id',
        'equipment_id',
        'name',
        'maintenance_type',
        'frequency_type',
        'frequency_value',
        'estimated_duration_hours',
        'description',
        'tasks',
        'is_active',
        'last_generated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tasks'                    => 'array',
            'is_active'                => 'boolean',
            'last_generated_at'        => 'datetime',
            'frequency_value'          => 'integer',
            'estimated_duration_hours' => 'decimal:2',
        ];
    }

    // Relations

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'maintenance_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Business logic

    /**
     * Calculate the next due date based on this plan's frequency settings.
     * For time-based frequencies the calculation is straightforward date arithmetic.
     * For usage-based frequencies (hours, kilometers) the same arithmetic is used
     * as a calendar approximation since actual meter readings are not tracked here.
     */
    public function calculateNextDueDate(\DateTime $fromDate): \DateTime
    {
        $next = clone $fromDate;
        $value = $this->frequency_value;

        switch ($this->frequency_type) {
            case self::FREQ_DAILY:
                $next->modify("+{$value} days");
                break;

            case self::FREQ_WEEKLY:
                $next->modify("+{$value} weeks");
                break;

            case self::FREQ_MONTHLY:
                $next->modify("+{$value} months");
                break;

            case self::FREQ_QUARTERLY:
                $months = $value * 3;
                $next->modify("+{$months} months");
                break;

            case self::FREQ_YEARLY:
                $next->modify("+{$value} years");
                break;

            case self::FREQ_HOURS:
            case self::FREQ_KILOMETERS:
                // Treat as days for calendar scheduling when no meter data available
                $next->modify("+{$value} days");
                break;

            default:
                $next->modify('+1 month');
        }

        return $next;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
