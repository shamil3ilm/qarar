<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingInvoice extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'organization_id', 'subscription_id',
        'billing_period_start', 'billing_period_end', 'invoice_date', 'due_date',
        'currency_code', 'subtotal', 'discount_amount', 'tax_amount', 'total',
        'amount_paid', 'amount_due', 'status', 'sent_at', 'paid_at', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id');
    }
}
