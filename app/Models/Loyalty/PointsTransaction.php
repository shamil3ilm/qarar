<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsTransaction extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'loyalty_account_id', 'transaction_type', 'points', 'balance_before',
        'balance_after', 'description', 'source_type', 'source_id', 'source_amount',
        'earn_multiplier', 'expires_at', 'is_expired', 'created_by',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'earn_multiplier' => 'decimal:2',
        'expires_at' => 'date',
        'is_expired' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyAccount::class, 'loyalty_account_id');
    }
}
