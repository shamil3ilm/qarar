<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseStrategyApproval extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'release_strategy_id',
        'level_id',
        'document_type',
        'document_id',
        'status',
        'approver_id',
        'comments',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(ReleaseStrategy::class, 'release_strategy_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ReleaseStrategyLevel::class, 'level_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
