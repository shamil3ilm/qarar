<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * GSTR-9 Annual GST Return.
 *
 * Filed annually by regular GST taxpayers, consolidating all monthly
 * GSTR-1 (outward supplies) and GSTR-3B (self-assessed tax) data.
 * Due date: 31 December of the year following the financial year end.
 */
class Gstr9Return extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_FILED    = 'filed';
    public const STATUS_ACCEPTED = 'accepted';

    protected $table = 'gstr9_returns';

    protected $fillable = [
        'organization_id',
        'gstin',
        'financial_year_start',
        't4a_taxable_supplies',
        't4b_zero_rated',
        't4c_nil_rated',
        't9_igst_payable',
        't9_cgst_payable',
        't9_sgst_payable',
        't9_cess_payable',
        't9_igst_paid',
        't9_cgst_paid',
        't9_sgst_paid',
        't9_cess_paid',
        't6a_itc_inputs',
        't6b_itc_input_services',
        't6c_itc_capital_goods',
        't6_total_itc',
        't7_itc_reversed',
        'net_itc',
        't18_late_fee_cgst',
        't18_late_fee_sgst',
        'status',
        'gstn_arn',
        'filed_date',
        'due_date',
        'notes',
        'prepared_by',
    ];

    protected $casts = [
        't4a_taxable_supplies'   => 'float',
        't4b_zero_rated'         => 'float',
        't4c_nil_rated'          => 'float',
        't9_igst_payable'        => 'float',
        't9_cgst_payable'        => 'float',
        't9_sgst_payable'        => 'float',
        't9_cess_payable'        => 'float',
        't9_igst_paid'           => 'float',
        't9_cgst_paid'           => 'float',
        't9_sgst_paid'           => 'float',
        't9_cess_paid'           => 'float',
        't6a_itc_inputs'         => 'float',
        't6b_itc_input_services' => 'float',
        't6c_itc_capital_goods'  => 'float',
        't6_total_itc'           => 'float',
        't7_itc_reversed'        => 'float',
        'net_itc'                => 'float',
        't18_late_fee_cgst'      => 'float',
        't18_late_fee_sgst'      => 'float',
        'filed_date'             => 'date',
        'due_date'               => 'date',
    ];

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Label: "FY 2024-25" */
    public function financialYearLabel(): string
    {
        $start = (int) $this->financial_year_start;
        return "FY {$start}-" . substr((string) ($start + 1), 2);
    }

    /** Total output tax payable. */
    public function totalTaxPayable(): float
    {
        return round(
            $this->t9_igst_payable + $this->t9_cgst_payable +
            $this->t9_sgst_payable + $this->t9_cess_payable,
            2
        );
    }

    /** Net tax liability after ITC setoff. */
    public function netTaxLiability(): float
    {
        return max(0.0, round($this->totalTaxPayable() - $this->net_itc, 2));
    }
}
