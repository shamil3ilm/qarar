<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class OnlinePayment extends Model
{
    use HasFactory;
    use BelongsToOrganization, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_CARD = 'card';
    public const METHOD_MADA = 'mada';
    public const METHOD_APPLE_PAY = 'apple_pay';

    protected $fillable = [
        'organization_id',
        'gateway_id',
        'payable_type',
        'payable_id',
        'external_payment_id',
        'status',
        'currency_code',
        'amount',
        'fee_amount',
        'net_amount',
        'payment_method',
        'card_brand',
        'card_last4',
        'gateway_response',
        'failure_reason',
        'ip_address',
        'payment_received_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'gateway_response' => 'array',
        ];
    }

    // Relationships
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    // Business logic
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCaptured(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }

    // Scopes
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByGateway($query, int $gatewayId)
    {
        return $query->where('gateway_id', $gatewayId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_CAPTURED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForPayable($query, string $type, int $id)
    {
        return $query->where('payable_type', $type)->where('payable_id', $id);
    }
}
