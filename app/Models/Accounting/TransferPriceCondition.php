<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferPriceCondition extends Model
{
    use HasUuid;

    public const TYPE_SURCHARGE = 'surcharge';
    public const TYPE_DISCOUNT  = 'discount';
    public const TYPE_FREIGHT   = 'freight';
    public const TYPE_DUTY      = 'duty';

    public const TYPES = [
        self::TYPE_SURCHARGE,
        self::TYPE_DISCOUNT,
        self::TYPE_FREIGHT,
        self::TYPE_DUTY,
    ];

    protected $fillable = [
        'transfer_price_id',
        'version_id',
        'condition_type',
        'amount',
        'is_percentage',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:4',
            'is_percentage' => 'boolean',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function transferPrice(): BelongsTo
    {
        return $this->belongsTo(TransferPrice::class, 'transfer_price_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TransferPriceVersion::class, 'version_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    /**
     * Resolve the absolute condition amount given a base price.
     */
    public function resolveAmount(float $basePrice): float
    {
        if ($this->is_percentage) {
            return $basePrice * ((float) $this->amount / 100);
        }

        return (float) $this->amount;
    }
}
