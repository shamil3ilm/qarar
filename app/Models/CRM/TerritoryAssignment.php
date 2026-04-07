<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerritoryAssignment extends Model
{
    use HasFactory;

    public const ROLE_OWNER  = 'owner';
    public const ROLE_BACKUP = 'backup';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'organization_id',
        'territory_id',
        'employee_id',
        'role',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();

        return $this->effective_from->toDateString() <= $today
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $today);
    }

    public function scopeActive($query)
    {
        $today = now()->toDateString();

        return $query->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            });
    }

    public function scopeOwners($query)
    {
        return $query->where('role', self::ROLE_OWNER);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
