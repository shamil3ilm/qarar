<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitClearanceItem extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'organization_id',
        'employee_exit_id',
        'department_id',
        'clearance_item',
        'responsible_person_id',
        'status',
        'cleared_at',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
        ];
    }

    public function employeeExit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCleared(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLEARED);
    }

    // Helpers

    public function clear(?string $remarks): void
    {
        $this->status     = self::STATUS_CLEARED;
        $this->cleared_at = now();
        $this->remarks    = $remarks;
        $this->save();
    }
}
