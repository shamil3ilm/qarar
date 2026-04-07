<?php

declare(strict_types=1);

namespace App\Models\Loyalty;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'loyalty_account_id', 'reward_id', 'points_transaction_id', 'points_spent',
        'status', 'redemption_code', 'invoice_id', 'fulfilled_at', 'expires_at', 'notes',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyAccount::class, 'loyalty_account_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(RewardsCatalogItem::class, 'reward_id');
    }

    public function pointsTransaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class);
    }
}
