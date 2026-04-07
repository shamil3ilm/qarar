<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesReturn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Refund extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    // Status values
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_CANCELLED = 'cancelled';

    // Refund types
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_VENDOR = 'vendor';

    // Refund methods
    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_ORIGINAL = 'original_payment_method';
    public const METHOD_WALLET = 'wallet';
    public const METHOD_CREDIT_NOTE = 'credit_note';

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function refundable(): MorphTo
    {
        return $this->morphTo('refundable');
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function markProcessed(int $userId, ?string $transactionReference = null): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_by' => $userId,
            'processed_at' => now(),
            'transaction_reference' => $transactionReference,
        ]);
    }
}