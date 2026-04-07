<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LoginHistory extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'login_history';

    // Statuses
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_2FA_REQUIRED = '2fa_required';

    public const STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
        self::STATUS_BLOCKED,
        self::STATUS_2FA_REQUIRED,
    ];

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'status',
        'failure_reason',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('attempted_at', '>=', now()->subDays($days));
    }

    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('attempted_at', [$from, $to]);
    }

    // Helpers

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
