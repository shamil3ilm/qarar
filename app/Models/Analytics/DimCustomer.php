<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimCustomer extends Model
{
    protected $table = 'dim_customer';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'customer_code',
        'customer_name',
        'customer_group',
        'country_code',
        'city',
        'currency_code',
        'credit_limit',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:4',
        'is_active'    => 'boolean',
        'synced_at'    => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function factSales(): HasMany
    {
        return $this->hasMany(FactSale::class);
    }
}
