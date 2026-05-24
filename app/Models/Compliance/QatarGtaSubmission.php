<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Qatar GTA e-invoice submission record.
 */
class QatarGtaSubmission extends Model
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

    protected $table = 'qatar_gta_submissions';

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
        'invoice_xml',
        'qr_code_data',
        'status',
        'gta_submission_id',
        'gta_response',
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
        'submitted_at'   => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
