<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompensationReview extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_APPLIED     = 'applied';

    protected $fillable = [
        'organization_id',
        'review_name',
        'review_date',
        'effective_date',
        'budget_amount',
        'allocated_amount',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'review_date'      => 'date',
            'effective_date'   => 'date',
            'budget_amount'    => 'decimal:4',
            'allocated_amount' => 'decimal:4',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompensationReviewItem::class, 'review_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getRemainingBudget(): float
    {
        return max(0.0, (float) $this->budget_amount - (float) $this->allocated_amount);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
