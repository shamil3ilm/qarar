<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrcCcmMonitor extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'grc_ccm_monitors';

    public const TYPE_PREVENTIVE = 'preventive';
    public const TYPE_DETECTIVE  = 'detective';
    public const TYPE_CORRECTIVE = 'corrective';

    public const FREQ_REAL_TIME = 'real_time';
    public const FREQ_HOURLY    = 'hourly';
    public const FREQ_DAILY     = 'daily';
    public const FREQ_WEEKLY    = 'weekly';
    public const FREQ_MONTHLY   = 'monthly';

    /** Frequency threshold in minutes for scheduling checks. */
    public const FREQUENCY_MINUTES = [
        self::FREQ_REAL_TIME => 0,
        self::FREQ_HOURLY    => 60,
        self::FREQ_DAILY     => 1440,
        self::FREQ_WEEKLY    => 10080,
        self::FREQ_MONTHLY   => 43200,
    ];

    protected $fillable = [
        'organization_id',
        'monitor_code',
        'name',
        'description',
        'control_type',
        'data_source',
        'rules',
        'frequency',
        'last_run_at',
        'total_exceptions',
        'is_active',
        'owner_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules'            => 'array',
            'last_run_at'      => 'datetime',
            'total_exceptions' => 'integer',
            'is_active'        => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(GrcCcmException::class, 'monitor_id');
    }

    public function isDue(): bool
    {
        if ($this->last_run_at === null) {
            return true;
        }

        $thresholdMinutes = self::FREQUENCY_MINUTES[$this->frequency] ?? 1440;

        if ($thresholdMinutes === 0) {
            return false; // real_time handled by event-driven approach
        }

        return $this->last_run_at->diffInMinutes(now()) >= $thresholdMinutes;
    }
}
