<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsignmentOrder extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const TYPE_FILLUP = 'fillup';
    public const TYPE_ISSUE  = 'issue';
    public const TYPE_PICKUP = 'pickup';
    public const TYPE_RETURN = 'return';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ConsignmentOrderLine::class, 'order_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('order_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT], true);
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeShipped(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function canBeCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_SHIPPED], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED], true);
    }
}
