<?php

declare(strict_types=1);

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddonPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id', 'addon_id', 'quantity', 'unit_price', 'total_price',
        'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'subscription_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAddon::class, 'addon_id');
    }
}
