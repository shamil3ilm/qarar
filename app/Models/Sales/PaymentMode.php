<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMode extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'code', 'type', 'description', 'icon',
        'bank_account_id', 'account_id', 'is_online', 'requires_reference',
        'requires_approval', 'surcharge_percent', 'surcharge_flat', 'min_amount',
        'max_amount', 'supported_currencies', 'gateway_provider', 'gateway_config',
        'is_active', 'display_order',
    ];

    protected $casts = [
        'surcharge_percent' => 'decimal:2',
        'surcharge_flat' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'supported_currencies' => 'array',
        'gateway_config' => 'array',
        'is_online' => 'boolean',
        'requires_reference' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\BankAccount::class ?? \stdClass::class, 'bank_account_id');
    }
}
