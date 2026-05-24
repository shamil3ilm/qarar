<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * UAE FTA e-invoice submission record.
 *
 * @property int    $id
 * @property string $uuid
 * @property int    $organization_id
 * @property int|null $invoice_id
 * @property string $invoice_number
 * @property string $invoice_type
 * @property string $issue_date
 * @property string $currency_code
 * @property string|null $seller_trn
 * @property string|null $buyer_trn
 * @property float  $subtotal
 * @property float  $tax_amount
 * @property float  $total_amount
 * @property float  $tax_rate
 * @property string|null $ubl_xml
 * @property string|null $qr_code_data
 * @property string $status
 * @property string|null $fta_submission_id
 * @property string|null $fta_response
 * @property string|null $billing_reference
 */
class FtaEInvoiceSubmission extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_INVOICE     = 'invoice';
    public const TYPE_CREDIT_NOTE = 'credit_note';
    public const TYPE_DEBIT_NOTE  = 'debit_note';

    /** UAE VAT standard rate (%) */
    public const UAE_VAT_RATE = 5.0;

    protected $table = 'fta_einvoice_submissions';

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'invoice_number',
        'invoice_type',
        'issue_date',
        'currency_code',
        'seller_trn',
        'buyer_trn',
        'subtotal',
        'tax_amount',
        'total_amount',
        'tax_rate',
        'ubl_xml',
        'qr_code_data',
        'status',
        'fta_submission_id',
        'fta_response',
        'submitted_at',
        'acknowledged_at',
        'billing_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal'       => 'float',
        'tax_amount'     => 'float',
        'total_amount'   => 'float',
        'tax_rate'       => 'float',
        'submitted_at'   => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
