<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class UserSession extends Model
{
    use HasFactory;
    public $timestamps = false;

    // Logout reasons
    public const LOGOUT_MANUAL = 'manual';
    public const LOGOUT_EXPIRED = 'expired';
    public const LOGOUT_FORCED = 'forced';

    public const LOGOUT_REASONS = [
        self::LOGOUT_MANUAL,
        self::LOGOUT_EXPIRED,
        self::LOGOUT_FORCED,
    ];

    // Device types
    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';

    public const DEVICE_TYPES = [
        self::DEVICE_DESKTOP,
        self::DEVICE_MOBILE,
        self::DEVICE_TABLET,
    ];

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'location',
        'login_at',
        'last_activity_at',
        'logout_at',
        'is_active',
        'logout_reason',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'logout_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopeRecentlyActive($query, int $minutes = 30)
    {
        return $query->where('last_activity_at', '>=', now()->subMinutes($minutes));
    }

    // Helpers

    public function isExpired(int $timeoutMinutes = 120): bool
    {
        return $this->last_activity_at->diffInMinutes(now()) > $timeoutMinutes;
    }

    public function terminate(string $reason = self::LOGOUT_FORCED): void
    {
        $this->update([
            'is_active' => false,
            'logout_at' => now(),
            'logout_reason' => $reason,
        ]);
    }

    public function touch(): bool
    {
        return $this->update(['last_activity_at' => now()]);
    }
}
