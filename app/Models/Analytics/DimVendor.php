<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimVendor extends Model
{
    protected $table = 'dim_vendor';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'vendor_code',
        'vendor_name',
        'vendor_group',
        'country_code',
        'currency_code',
        'payment_terms',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function factPurchases(): HasMany
    {
        return $this->hasMany(FactPurchase::class);
    }
}
