<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemAnnouncement extends Model
{
    use HasFactory;
    use HasUuid;

    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_FEATURE = 'feature';
    public const TYPE_CRITICAL = 'critical';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_ORGANIZATIONS = 'organizations';
    public const AUDIENCE_ADMINS = 'admins';
    public const AUDIENCE_SPECIFIC = 'specific';

    protected $fillable = [
        'admin_id',
        'title',
        'content',
        'type',
        'target_audience',
        'target_organization_ids',
        'target_subscription_plans',
        'is_dismissible',
        'show_banner',
        'banner_color',
        'action_url',
        'action_text',
        'starts_at',
        'ends_at',
        'status',
        'published_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_organization_ids' => 'array',
            'target_subscription_plans' => 'array',
            'is_dismissible' => 'boolean',
            'show_banner' => 'boolean',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class, 'announcement_id');
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where(function ($q) use ($audience) {
            $q->where('target_audience', self::AUDIENCE_ALL)
                ->orWhere('target_audience', $audience);
        });
    }
}
