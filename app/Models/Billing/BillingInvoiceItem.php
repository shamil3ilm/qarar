<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'item_type', 'description', 'quantity', 'unit_label',
        'unit_price', 'discount_amount', 'tax_rate', 'tax_amount', 'total',
        'plan_id', 'addon_id', 'metric_type', 'line_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAddon::class, 'addon_id');
    }
}
