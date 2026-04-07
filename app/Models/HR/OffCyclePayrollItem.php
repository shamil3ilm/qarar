<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffCyclePayrollItem extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'off_cycle_payroll_run_id',
        'employee_id',
        'component_code',
        'component_name',
        'amount',
        'tax_amount',
        'net_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OffCyclePayrollRun::class, 'off_cycle_payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
