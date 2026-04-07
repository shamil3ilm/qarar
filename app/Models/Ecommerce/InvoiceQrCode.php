<?php

declare(strict_types=1);

namespace App\Models\Ecommerce;

use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class InvoiceQrCode extends Model
{
    use HasFactory;
    public const TYPE_ZATCA = 'zatca';
    public const TYPE_PAYMENT_LINK = 'payment_link';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'invoice_id',
        'qr_type',
        'qr_data',
        'qr_image_path',
        'payment_link',
        'payment_amount',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // Business logic
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isPaymentLink(): bool
    {
        return $this->qr_type === self::TYPE_PAYMENT_LINK;
    }

    public function isZatca(): bool
    {
        return $this->qr_type === self::TYPE_ZATCA;
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('qr_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
