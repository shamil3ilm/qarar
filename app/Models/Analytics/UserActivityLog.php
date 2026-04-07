<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';
    public const DEVICE_DESKTOP = 'desktop';

    protected $fillable = [
        'uuid',
        'user_id',
        'organization_id',
        'method',
        'route_name',
        'module',
        'action',
        'entity_type',
        'entity_id',
        'response_status',
        'duration_ms',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'request_summary',
    ];

    protected $casts = [
        'request_summary' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
