<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageAlert extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'metric_type', 'threshold_percent', 'threshold_value',
        'current_value', 'status', 'notified_at', 'resolved_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
