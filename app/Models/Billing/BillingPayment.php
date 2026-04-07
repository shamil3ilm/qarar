<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $fillable = [
        'transaction_id', 'organization_id', 'invoice_id', 'payment_method_id',
        'amount', 'currency_code', 'payment_type', 'provider',
        'provider_transaction_id', 'status', 'processed_at', 'failure_reason',
        'failure_code', 'is_refunded', 'refunded_amount', 'refunded_at',
        'refund_reason', 'provider_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'is_refunded' => 'boolean',
        'provider_response' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(BillingPaymentMethod::class, 'payment_method_id');
    }
}
