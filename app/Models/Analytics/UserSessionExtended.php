<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSessionExtended extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $table = 'user_sessions_extended';

    protected $fillable = [
        'uuid',
        'user_id',
        'organization_id',
        'session_token_hash',
        'ip_address',
        'user_agent',
        'device_type',
        'country_code',
        'city',
        'started_at',
        'last_active_at',
        'ended_at',
        'duration_seconds',
        'request_count',
        'modules_accessed',
        'end_reason',
    ];

    protected $casts = [
        'modules_accessed' => 'array',
        'started_at' => 'datetime',
        'last_active_at' => 'datetime',
        'ended_at' => 'datetime',
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
