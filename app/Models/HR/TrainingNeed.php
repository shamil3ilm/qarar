<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingNeed extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $table = 'training_needs';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
    ];

    public const STATUS_IDENTIFIED = 'identified';
    public const STATUS_PLANNED    = 'planned';
    public const STATUS_FULFILLED  = 'fulfilled';
    public const STATUS_CANCELLED  = 'cancelled';

    public const STATUSES = [
        self::STATUS_IDENTIFIED,
        self::STATUS_PLANNED,
        self::STATUS_FULFILLED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'employee_id',
        'department_id',
        'course_id',
        'title',
        'description',
        'priority',
        'status',
        'identified_by',
        'target_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', [self::STATUS_IDENTIFIED, self::STATUS_PLANNED]);
    }
}
