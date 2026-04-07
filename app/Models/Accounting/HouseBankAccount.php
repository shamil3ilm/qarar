<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseBankAccount extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'daily_payment_limit' => 'decimal:4',
        'is_active'           => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function houseBank(): BelongsTo
    {
        return $this->belongsTo(HouseBank::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
