<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalibrationOrder extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_PLANNED     = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_OVERDUE     = 'overdue';
    public const STATUS_CANCELLED   = 'cancelled';

    public const RESULT_PASS        = 'pass';
    public const RESULT_FAIL        = 'fail';
    public const RESULT_CONDITIONAL = 'conditional';

    protected $fillable = [
        'organization_id',
        'calibration_equipment_id',
        'calibration_plan_id',
        'order_number',
        'scheduled_date',
        'completed_date',
        'status',
        'calibrated_by',
        'external_lab',
        'result',
        'actual_measurement',
        'notes',
        'next_calibration_date',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date'       => 'date',
            'completed_date'       => 'date',
            'next_calibration_date' => 'date',
            'actual_measurement'   => 'decimal:4',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(CalibrationEquipment::class, 'calibration_equipment_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CalibrationPlan::class, 'calibration_plan_id');
    }

    public function calibratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calibrated_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(CalibrationCertificate::class);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE)
            ->orWhere(function ($q) {
                $q->where('status', self::STATUS_PLANNED)
                    ->where('scheduled_date', '<', now()->toDateString());
            });
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE
            || ($this->status === self::STATUS_PLANNED && $this->scheduled_date < now()->toDateString());
    }

    public static function generateOrderNumber(int $orgId): string
    {
        $year   = now()->format('Y');
        $prefix = "CAL-{$year}-";

        $last = static::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('order_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('order_number');

        $seq = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
