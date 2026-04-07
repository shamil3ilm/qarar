<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageAggregate extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'metric_type', 'period_type', 'period',
        'total_quantity', 'peak_quantity', 'average_quantity', 'breakdown',
    ];

    protected $casts = [
        'average_quantity' => 'decimal:2',
        'breakdown' => 'array',
    ];
}
