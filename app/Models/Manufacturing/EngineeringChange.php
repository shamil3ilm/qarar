<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EngineeringChange extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_IMPLEMENTED = 'implemented';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'organization_id',
        'change_number',
        'change_type',
        'description',
        'reason',
        'status',
        'effectivity_date',
        'priority',
        'requested_by',
        'approved_by',
        'approved_at',
        'implemented_at',
    ];

    protected $casts = [
        'effectivity_date' => 'date',
        'approved_at' => 'datetime',
        'implemented_at' => 'datetime',
    ];

    // Relationships

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function affectedObjects(): HasMany
    {
        return $this->hasMany(EcmAffectedObject::class);
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->whereHas('affectedObjects', function (Builder $q) use ($productId): void {
            $q->where('object_type', 'product')->where('object_id', $productId);
        });
    }

    // Helpers

    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canImplement(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
