<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'code', 'type', 'options', 'unit',
        'is_filterable', 'is_comparable', 'is_visible', 'display_order', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_filterable' => 'boolean',
        'is_comparable' => 'boolean',
        'is_visible' => 'boolean',
        'is_active' => 'boolean',
    ];
}
