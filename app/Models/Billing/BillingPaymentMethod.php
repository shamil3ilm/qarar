<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingPaymentMethod extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id', 'type', 'provider', 'provider_payment_method_id',
        'card_brand', 'card_last_four', 'card_exp_month', 'card_exp_year',
        'bank_name', 'bank_account_last_four', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
