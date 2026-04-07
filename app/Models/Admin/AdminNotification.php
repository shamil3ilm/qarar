<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class AdminNotification extends Model
{
    use HasFactory;
    use HasUuid;

    public const TYPE_NEW_ORGANIZATION = 'new_organization';
    public const TYPE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    public const TYPE_SYSTEM_ALERT = 'system_alert';
    public const TYPE_SUPPORT_TICKET = 'support_ticket';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'admin_id',
        'type',
        'title',
        'message',
        'severity',
        'data',
        'action_url',
        'is_read',
        'read_at',
        'is_dismissed',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'is_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function dismiss(): void
    {
        $this->update([
            'is_dismissed' => true,
            'dismissed_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}
