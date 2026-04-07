<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCredit extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'balance', 'total_credited', 'total_used', 'currency_code',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_credited' => 'decimal:2',
        'total_used' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingCreditTransaction::class, 'organization_id', 'organization_id');
    }
}
