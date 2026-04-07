<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOverrideReason extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'name', 'code', 'description', 'requires_approval',
        'requires_evidence', 'is_active', 'display_order',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'requires_evidence' => 'boolean',
        'is_active' => 'boolean',
    ];
}
