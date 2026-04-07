<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashSale extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_OPEN      = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED    = 'voided';

    public const PAYMENT_CASH   = 'cash';
    public const PAYMENT_CARD   = 'card';
    public const PAYMENT_WALLET = 'wallet';
    public const PAYMENT_MIXED  = 'mixed';

    protected $fillable = [
        'organization_id',
        'cash_sale_number',
        'customer_id',
        'cashier_id',
        'branch_id',
        'sale_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'payment_method',
        'amount_tendered',
        'change_given',
        'invoice_id',
        'status',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'sale_date'       => 'datetime',
        'subtotal'        => 'decimal:4',
        'tax_amount'      => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_amount'    => 'decimal:4',
        'amount_tendered' => 'decimal:4',
        'change_given'    => 'decimal:4',
        'voided_at'       => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashSaleLine::class);
    }
}
