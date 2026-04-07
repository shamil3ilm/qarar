<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitChange extends Model
{
    use HasUuid;

    protected $table = 'benefit_changes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function employeeBenefit(): BelongsTo
    {
        return $this->belongsTo(EmployeeBenefit::class, 'employee_benefit_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
