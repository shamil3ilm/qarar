<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingEnrollment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'training_enrollments';

    public const STATUS_ENROLLED   = 'enrolled';
    public const STATUS_ATTENDED   = 'attended';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_NO_SHOW    = 'no_show';

    public const STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_ATTENDED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    protected $fillable = [
        'organization_id',
        'session_id',
        'employee_id',
        'status',
        'enrolled_at',
        'completion_date',
        'score',
        'feedback',
        'enrolled_by',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at'     => 'datetime',
            'completion_date' => 'date',
            'score'           => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function certification(): HasOne
    {
        return $this->hasOne(TrainingCertification::class, 'enrollment_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function pass(float $score, int $userId): self
    {
        $this->status          = self::STATUS_COMPLETED;
        $this->score           = $score;
        $this->completion_date = now()->toDateString();
        $this->save();

        return $this;
    }

    public function fail(float $score, int $userId): self
    {
        $this->status          = self::STATUS_FAILED;
        $this->score           = $score;
        $this->completion_date = now()->toDateString();
        $this->save();

        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function scopeForEmployee(\Illuminate\Database\Eloquent\Builder $query, int $employeeId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
