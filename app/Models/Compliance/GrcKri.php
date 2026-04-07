<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrcKri extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_kris';

    // Aggregation constants
    public const AGGREGATION_COUNT      = 'count';
    public const AGGREGATION_SUM        = 'sum';
    public const AGGREGATION_AVG        = 'avg';
    public const AGGREGATION_MAX        = 'max';
    public const AGGREGATION_MIN        = 'min';
    public const AGGREGATION_PERCENTAGE = 'percentage';

    // Direction constants
    public const DIRECTION_LOWER_BETTER  = 'lower_is_better';
    public const DIRECTION_HIGHER_BETTER = 'higher_is_better';

    // Status constants
    public const STATUS_GREEN = 'green';
    public const STATUS_AMBER = 'amber';
    public const STATUS_RED   = 'red';

    // Frequency constants
    public const FREQ_DAILY     = 'daily';
    public const FREQ_WEEKLY    = 'weekly';
    public const FREQ_MONTHLY   = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';

    /** Frequency threshold in days for scheduling checks. */
    public const FREQUENCY_DAYS = [
        self::FREQ_DAILY     => 1,
        self::FREQ_WEEKLY    => 7,
        self::FREQ_MONTHLY   => 30,
        self::FREQ_QUARTERLY => 90,
    ];

    protected $fillable = [
        'organization_id',
        'risk_id',
        'kri_code',
        'name',
        'description',
        'data_source',
        'metric_field',
        'aggregation',
        'threshold_green',
        'threshold_amber',
        'threshold_red',
        'direction',
        'frequency',
        'last_measured_at',
        'last_value',
        'last_status',
        'is_active',
        'owner_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'threshold_green'  => 'decimal:4',
            'threshold_amber'  => 'decimal:4',
            'threshold_red'    => 'decimal:4',
            'last_value'       => 'decimal:4',
            'last_measured_at' => 'datetime',
            'is_active'        => 'boolean',
        ];
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(GrcRisk::class, 'risk_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(GrcKriReading::class, 'kri_id');
    }

    /**
     * Determine whether this KRI is due for a new reading based on its frequency.
     */
    public function isDue(): bool
    {
        if ($this->last_measured_at === null) {
            return true;
        }

        $thresholdDays = self::FREQUENCY_DAYS[$this->frequency] ?? 30;

        return $this->last_measured_at->diffInDays(now()) >= $thresholdDays;
    }
}
