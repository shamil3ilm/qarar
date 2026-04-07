<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExchangeOrder extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const DIFF_PAYMENT = 'payment';
    public const DIFF_CREDIT_NOTE = 'credit_note';
    public const DIFF_WAIVED = 'waived';

    protected $fillable = [
        'organization_id',
        'exchange_number',
        'sales_return_id',
        'customer_id',
        'original_total',
        'exchange_total',
        'price_difference',
        'difference_resolution',
        'new_invoice_id',
        'new_sales_order_id',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_total' => 'decimal:2',
            'exchange_total' => 'decimal:2',
            'price_difference' => 'decimal:2',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExchangeOrderItem::class);
    }

    public function newInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'new_invoice_id');
    }

    public function newSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'new_sales_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerPaysMore(): bool
    {
        return bccomp((string) $this->price_difference, '0', 2) > 0;
    }

    public function customerGetsRefund(): bool
    {
        return bccomp((string) $this->price_difference, '0', 2) < 0;
    }
}
