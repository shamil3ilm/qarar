<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalSession extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    protected $table = 'portal_sessions';

    protected $fillable = [
        'organization_id',
        'portal_user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'expires_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'        => 'datetime',
            'last_activity_at'  => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
