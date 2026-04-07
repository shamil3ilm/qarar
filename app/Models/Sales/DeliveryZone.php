<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'code', 'countries', 'states', 'cities',
        'postal_codes', 'is_active',
    ];

    protected $casts = [
        'countries' => 'array',
        'states' => 'array',
        'cities' => 'array',
        'postal_codes' => 'array',
        'is_active' => 'boolean',
    ];
}
