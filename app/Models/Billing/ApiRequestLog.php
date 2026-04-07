<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'user_id', 'endpoint', 'method', 'response_status',
        'response_time_ms', 'ip_address', 'api_version', 'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];
}
