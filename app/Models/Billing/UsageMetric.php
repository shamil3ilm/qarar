<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageMetric extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'metric_type', 'quantity', 'metric_date', 'billing_period',
    ];

    protected $casts = [
        'metric_date' => 'date',
    ];
}
