<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bahrain VAT Return (NBR quarterly/monthly filing).
 *
 * VAT rate: 10% (since 1 January 2022).
 */
class BahrainVatReturn extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_PAID      = 'paid';

    /** Current Bahrain VAT rate (%) */
    public const VAT_RATE = 10.0;

    protected $table = 'bahrain_vat_returns';

    protected $fillable = [
        'organization_id',
        'period_type',
        'period_quarter',
        'period_month',
        'period_year',
        'period_start',
        'period_end',
        'standard_rated_supplies',
        'zero_rated_supplies',
        'exempt_supplies',
        'output_vat',
        'standard_rated_purchases',
        'capital_goods_input_tax',
        'total_input_vat',
        'net_vat_payable',
        'vat_rate',
        'status',
        'nbr_reference',
        'filing_due_date',
        'filed_at',
        'notes',
        'prepared_by',
    ];

    protected $casts = [
        'standard_rated_supplies'  => 'float',
        'zero_rated_supplies'      => 'float',
        'exempt_supplies'          => 'float',
        'output_vat'               => 'float',
        'standard_rated_purchases' => 'float',
        'capital_goods_input_tax'  => 'float',
        'total_input_vat'          => 'float',
        'net_vat_payable'          => 'float',
        'vat_rate'                 => 'float',
        'period_start'             => 'date',
        'period_end'               => 'date',
        'filing_due_date'          => 'date',
        'filed_at'                 => 'date',
    ];

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** True when the return results in a VAT refund (input > output). */
    public function isRefund(): bool
    {
        return $this->net_vat_payable < 0;
    }
}
