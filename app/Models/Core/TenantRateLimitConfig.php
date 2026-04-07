<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TenantRateLimitConfig extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    protected $fillable = [
        'uuid', 'organization_id', 'requests_per_minute', 'requests_per_hour',
        'requests_per_day', 'burst_limit', 'api_key_limit', 'is_unlimited', 'custom_limits',
    ];

    protected $casts = [
        'is_unlimited'  => 'boolean',
        'custom_limits' => 'array',
    ];
}
