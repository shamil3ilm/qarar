<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HouseBank extends Model
{
    use HasUuid, BelongsToOrganization, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function accounts(): HasMany
    {
        return $this->hasMany(HouseBankAccount::class);
    }

    public function paymentAdvices(): HasMany
    {
        return $this->hasMany(PaymentAdvice::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
