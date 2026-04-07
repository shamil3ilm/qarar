<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftAssignment extends Model
{
    use BelongsToOrganization;

    protected $table = 'employee_shift_assignments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        $today = now()->toDateString();

        return $this->effective_from->lte(now())
            && ($this->effective_to === null || $this->effective_to->gte(now()));
    }

    public function scopeCurrent($query)
    {
        $today = now()->toDateString();

        return $query->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            });
    }
}
