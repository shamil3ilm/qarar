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

class VendorAdvanceRequest extends Model
{
    use BelongsToOrganization, HasAuditTrail, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'approved_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorAdvancePayment::class, 'advance_request_id');
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getPaidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmount(): float
    {
        return (float) bcsub((string) $this->requested_amount, (string) $this->getPaidAmount(), 4);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_APPROVED]);
    }
}
