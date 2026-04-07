<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagerDelegation extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_FULL = 'full';
    public const TYPE_LEAVE_APPROVAL = 'leave_approval';
    public const TYPE_ATTENDANCE_APPROVAL = 'attendance_approval';
    public const TYPE_EXPENSE_APPROVAL = 'expense_approval';

    protected $fillable = [
        'organization_id',
        'manager_id',
        'delegate_id',
        'delegation_type',
        'valid_from',
        'valid_to',
        'is_active',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForManager(Builder $query, int $managerId): Builder
    {
        return $query->where('manager_id', $managerId);
    }

    public function scopeForDelegate(Builder $query, int $delegateId): Builder
    {
        return $query->where('delegate_id', $delegateId);
    }

    // Helpers

    public function isValidNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->valid_from->toDateString() > $today) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->toDateString() < $today) {
            return false;
        }

        return true;
    }
}
