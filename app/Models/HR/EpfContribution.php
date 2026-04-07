<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpfContribution extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'epf_contributions';

    protected $guarded = ['id'];

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_CHALLAN_PAID = 'challan_paid';

    protected function casts(): array
    {
        return [
            'pf_wage'                    => 'decimal:2',
            'employee_contribution'      => 'decimal:2',
            'employer_epf_contribution'  => 'decimal:2',
            'employer_eps_contribution'  => 'decimal:2',
            'edli_contribution'          => 'decimal:2',
            'admin_charges'              => 'decimal:2',
            'challan_due_date'           => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function totalEmployerContribution(): float
    {
        return (float) $this->employer_epf_contribution
            + (float) $this->employer_eps_contribution
            + (float) $this->edli_contribution
            + (float) $this->admin_charges;
    }
}
