<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsiContribution extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'esi_contributions';

    protected $guarded = ['id'];

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_CHALLAN_PAID = 'challan_paid';

    /** Monthly gross threshold above which ESI is not applicable (₹21,000). */
    public const ESI_GROSS_CEILING = 21000.00;

    protected function casts(): array
    {
        return [
            'gross_wage'            => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'is_applicable'         => 'boolean',
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
}
