<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * UAE Corporate Income Tax (CIT) assessment record.
 *
 * 9% rate on taxable income above AED 375,000 (Federal Decree-Law No. 47/2022).
 */
class UaeCitAssessment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ASSESSED  = 'assessed';
    public const STATUS_PAID      = 'paid';

    /** UAE CIT rate (%) */
    public const CIT_RATE = 9.0;

    /** Income threshold below which 0% applies (AED) */
    public const ZERO_RATE_THRESHOLD = 375_000.0;

    /** Small Business Relief revenue ceiling (AED) */
    public const SMALL_BUSINESS_THRESHOLD = 3_000_000.0;

    protected $table = 'uae_cit_assessments';

    protected $fillable = [
        'organization_id',
        'fiscal_year_id',
        'tax_year',
        'accounting_income',
        'add_backs',
        'deductions',
        'taxable_income',
        'zero_rate_threshold',
        'small_business_threshold',
        'cit_rate',
        'small_business_relief',
        'cit_due',
        'cit_paid',
        'cit_remaining',
        'status',
        'emara_tax_reference',
        'filing_due_date',
        'filed_at',
        'notes',
        'prepared_by',
    ];

    protected $casts = [
        'accounting_income'       => 'float',
        'add_backs'               => 'float',
        'deductions'              => 'float',
        'taxable_income'          => 'float',
        'zero_rate_threshold'     => 'float',
        'small_business_threshold' => 'float',
        'cit_rate'                => 'float',
        'small_business_relief'   => 'boolean',
        'cit_due'                 => 'float',
        'cit_paid'                => 'float',
        'cit_remaining'           => 'float',
        'filing_due_date'         => 'date',
        'filed_at'                => 'date',
    ];

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function outstandingBalance(): float
    {
        return max(0.0, (float) bcsub((string) $this->cit_due, (string) $this->cit_paid, 4));
    }
}
