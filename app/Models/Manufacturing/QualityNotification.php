<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityNotification extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    // Notification type constants
    public const TYPE_DEFECT = 'defect';
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_IMPROVEMENT = 'improvement';
    public const TYPE_DEVIATION = 'deviation';

    // Source type constants
    public const SOURCE_INSPECTION_LOT = 'inspection_lot';
    public const SOURCE_CUSTOMER = 'customer';
    public const SOURCE_SUPPLIER = 'supplier';
    public const SOURCE_INTERNAL = 'internal';

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id',
        'notification_number',
        'notification_type',
        'source_type',
        'source_id',
        'product_id',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
        'root_cause',
        'corrective_action',
        'preventive_action',
        'due_date',
        'resolved_at',
        'resolved_by',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    // Relationships

    public function defects(): HasMany
    {
        return $this->hasMany(DefectRecord::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->where('due_date', '<', now()->toDateString());
    }

    // Helper Methods

    /**
     * Resolve the notification by recording root cause and corrective action,
     * then transitioning to resolved status.
     *
     * Returns a fresh instance with updated attributes.
     */
    public function resolve(string $rootCause, string $correctiveAction, int $userId): self
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'root_cause' => $rootCause,
            'corrective_action' => $correctiveAction,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);

        return $this->fresh();
    }

    /**
     * Close a resolved notification.
     *
     * Returns a fresh instance with updated attributes.
     */
    public function close(int $userId): self
    {
        $this->update(['status' => self::STATUS_CLOSED]);

        return $this->fresh();
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCritical(): bool
    {
        return $this->priority === self::PRIORITY_CRITICAL;
    }

    public function canBeResolved(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    public function canBeClosed(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
