<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EosbProvision extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'eosb_provisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'days_earned' => 'decimal:4',
            'daily_rate' => 'decimal:4',
            'provision_amount' => 'decimal:4',
            'cumulative_amount' => 'decimal:4',
            'basic_salary_used' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(EosbPolicy::class, 'eosb_policy_id');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }
}
