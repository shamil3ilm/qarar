<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalOrder extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const STATUS_CREATED               = 'created';
    public const STATUS_RELEASED              = 'released';
    public const STATUS_TECHNICALLY_COMPLETED = 'technically_completed';
    public const STATUS_CLOSED                = 'closed';

    public const TYPE_OVERHEAD   = 'overhead';
    public const TYPE_INVESTMENT  = 'investment';
    public const TYPE_ACCRUAL     = 'accrual';
    public const TYPE_STATISTICAL = 'statistical';

    protected $fillable = [
        'organization_id',
        'order_number',
        'description',
        'order_type',
        'cost_center_id',
        'responsible_user_id',
        'start_date',
        'end_date',
        'budget_amount',
        'committed_amount',
        'actual_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date'       => 'date',
            'end_date'         => 'date',
            'budget_amount'    => 'decimal:4',
            'committed_amount' => 'decimal:4',
            'actual_amount'    => 'decimal:4',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(InternalOrderSettlement::class, 'internal_order_id');
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isTechnicallyCompleted(): bool
    {
        return $this->status === self::STATUS_TECHNICALLY_COMPLETED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }
}
