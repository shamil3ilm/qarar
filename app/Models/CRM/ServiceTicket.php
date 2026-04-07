<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Sales\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_CUSTOMER = 'pending_customer';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    // Type constants
    public const TYPE_BUG = 'bug';
    public const TYPE_FEATURE_REQUEST = 'feature_request';
    public const TYPE_BILLING = 'billing';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_GENERAL = 'general';

    // Source constants
    public const SOURCE_EMAIL = 'email';
    public const SOURCE_PHONE = 'phone';
    public const SOURCE_PORTAL = 'portal';
    public const SOURCE_CHAT = 'chat';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'ticket_number',
        'subject',
        'description',
        'status',
        'priority',
        'type',
        'source',
        'contact_id',
        'assigned_to',
        'team_id',
        'sla_policy_id',
        'first_response_due_at',
        'resolution_due_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'sla_breached',
        'resolution_notes',
        'customer_rating',
        'customer_feedback',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'first_response_due_at' => 'datetime',
            'resolution_due_at'     => 'datetime',
            'first_response_at'     => 'datetime',
            'resolved_at'           => 'datetime',
            'closed_at'             => 'datetime',
            'sla_breached'          => 'boolean',
            'customer_rating'       => 'integer',
        ];
    }

    // Relationships

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ServiceTicketComment::class, 'ticket_id')->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Business logic

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_CUSTOMER,
        ], true);
    }

    public function isOverdue(): bool
    {
        if (!$this->isActive() || !$this->resolution_due_at) {
            return false;
        }

        return $this->resolution_due_at->isPast();
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_breached;
    }

    public function hasFirstResponseSlaBreached(): bool
    {
        if ($this->first_response_at) {
            return $this->first_response_due_at
                && $this->first_response_at->gt($this->first_response_due_at);
        }

        return $this->first_response_due_at && $this->first_response_due_at->isPast();
    }

    public function hasResolutionSlaBreached(): bool
    {
        if ($this->resolved_at) {
            return $this->resolution_due_at
                && $this->resolved_at->gt($this->resolution_due_at);
        }

        return $this->resolution_due_at && $this->resolution_due_at->isPast() && $this->isActive();
    }

    /**
     * Apply SLA policy to set deadline fields.
     */
    public function calculateSlaDeadlines(): void
    {
        if (!$this->slaPolicy) {
            return;
        }

        $deadlines = $this->slaPolicy->calculateDeadlines($this->created_at ?? Carbon::now());
        $this->first_response_due_at = $deadlines['first_response_due_at'];
        $this->resolution_due_at = $deadlines['resolution_due_at'];
    }

    /**
     * Mark the ticket as resolved.
     */
    public function markResolved(string $notes): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_at = now();
        $this->resolution_notes = $notes;

        if ($this->resolution_due_at && now()->gt($this->resolution_due_at)) {
            $this->sla_breached = true;
        }

        $this->save();
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_CUSTOMER,
        ]);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_CUSTOMER,
        ])->where('resolution_due_at', '<', now());
    }

    public function scopeBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }
}
