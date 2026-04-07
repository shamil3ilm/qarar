<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class SupportTicket extends Model
{
    use HasFactory;
    use HasUuid, SoftDeletes;

    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_FEATURE_REQUEST = 'feature_request';
    public const CATEGORY_BUG_REPORT = 'bug_report';
    public const CATEGORY_GENERAL = 'general';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_RESPONSE = 'waiting_response';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const SOURCE_WEB = 'web';
    public const SOURCE_EMAIL = 'email';
    public const SOURCE_API = 'api';
    public const SOURCE_PHONE = 'phone';

    protected $fillable = [
        'ticket_number',
        'organization_id',
        'user_id',
        'assigned_admin_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'tags',
        'source',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'satisfaction_rating',
        'satisfaction_feedback',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'satisfaction_rating' => 'decimal:1',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_RESPONSE], true);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_RESPONSE]);
    }

    public function scopeAssignedTo($query, int $adminId)
    {
        return $query->where('assigned_admin_id', $adminId);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_admin_id');
    }
}
