<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    public const TYPE_SALES = 'sales';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SERVICE = 'service';
    public const TYPE_MAINTENANCE = 'maintenance';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_date' => 'date',
            'auto_renew' => 'boolean',
            'renewal_notice_days' => 'integer',
            'total_value' => 'decimal:4',
            'billed_amount' => 'decimal:4',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function childContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class)->orderBy('sort_order');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ContractRelease::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class)->orderBy('due_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast() || $this->status === self::STATUS_EXPIRED;
    }

    public function canBeActivated(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeTerminated(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_DRAFT], true);
    }

    public function getRemainingValue(): float
    {
        if ($this->total_value === null) {
            return 0.0;
        }

        return (float) bcsub((string) $this->total_value, (string) $this->billed_amount, 4);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('end_date', '<=', now()->addDays($days))
            ->where('end_date', '>=', now());
    }
}
