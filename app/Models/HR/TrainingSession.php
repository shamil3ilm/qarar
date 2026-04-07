<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'training_sessions';

    public const STATUS_SCHEDULED   = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'course_id',
        'session_number',
        'trainer_name',
        'location',
        'meeting_link',
        'start_date',
        'end_date',
        'max_participants',
        'enrolled_count',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'       => 'datetime',
            'end_date'         => 'datetime',
            'max_participants' => 'integer',
            'enrolled_count'   => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasAvailableSlots(): bool
    {
        if ($this->max_participants === null) {
            return true;
        }

        return $this->enrolled_count < $this->max_participants;
    }

    public function getDuration(): float
    {
        $startDate = $this->start_date instanceof \Carbon\Carbon
            ? $this->start_date
            : \Carbon\Carbon::parse($this->start_date);

        $endDate = $this->end_date instanceof \Carbon\Carbon
            ? $this->end_date
            : \Carbon\Carbon::parse($this->end_date);

        return round($startDate->diffInMinutes($endDate) / 60, 2);
    }

    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->start_date > now();
    }

    public function start(int $userId): self
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->save();

        return $this;
    }

    public function complete(int $userId): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->save();

        return $this;
    }

    public function scopeUpcoming(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('start_date', '>', now());
    }

    public function scopeForCourse(\Illuminate\Database\Eloquent\Builder $query, int $courseId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('course_id', $courseId);
    }
}
