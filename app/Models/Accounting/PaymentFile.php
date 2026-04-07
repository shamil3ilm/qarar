<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentFile extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const FORMAT_SEPA_CT    = 'sepa_ct';
    public const FORMAT_SEPA_DD    = 'sepa_dd';
    public const FORMAT_ISO20022   = 'iso20022';
    public const FORMAT_ACH        = 'ach';
    public const FORMAT_BACS       = 'bacs';
    public const FORMAT_SWIFT_MT103 = 'swift_mt103';

    public const STATUS_GENERATED    = 'generated';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_REJECTED     = 'rejected';

    protected $fillable = [
        'organization_id',
        'payment_run_id',
        'file_format',
        'file_name',
        'file_content',
        'message_id',
        'creation_datetime',
        'number_of_transactions',
        'total_amount',
        'currency_code',
        'status',
        'submitted_at',
        'acknowledged_at',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'creation_datetime'    => 'datetime',
            'submitted_at'         => 'datetime',
            'acknowledged_at'      => 'datetime',
            'total_amount'         => 'decimal:4',
            'number_of_transactions' => 'integer',
        ];
    }

    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
