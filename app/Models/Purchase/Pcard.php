<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\CostCenter;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pcard extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasUuid, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'card_number_masked',
        'card_holder_id',
        'cost_center_id',
        'credit_limit',
        'currency',
        'valid_from',
        'valid_to',
        'status',
        'single_transaction_limit',
        'monthly_limit',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'credit_limit' => 'decimal:4',
            'single_transaction_limit' => 'decimal:4',
            'monthly_limit' => 'decimal:4',
        ];
    }

    public function cardHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'card_holder_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(PcardStatement::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->valid_to && $this->valid_to->isPast();
    }
}
