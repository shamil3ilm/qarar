<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubcontractOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT                = 'draft';
    public const STATUS_SENT                 = 'sent';
    public const STATUS_MATERIAL_TRANSFERRED = 'material_transferred';
    public const STATUS_IN_PROCESS           = 'in_process';
    public const STATUS_RECEIVED             = 'received';
    public const STATUS_CLOSED               = 'closed';
    public const STATUS_CANCELLED            = 'cancelled';

    protected $guarded = ['id'];

    protected $casts = [
        'issued_date'           => 'date',
        'expected_receipt_date' => 'date',
        'service_charge'        => 'decimal:4',
    ];

    // Relationships

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubcontractOrderLine::class, 'order_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SubcontractComponent::class, 'order_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(SubcontractTransfer::class, 'order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(SubcontractReceipt::class, 'order_id');
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_MATERIAL_TRANSFERRED,
            self::STATUS_IN_PROCESS,
        ]);
    }

    public function scopeForVendor($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isMaterialTransferred(): bool
    {
        return $this->status === self::STATUS_MATERIAL_TRANSFERRED;
    }

    public function isInProcess(): bool
    {
        return $this->status === self::STATUS_IN_PROCESS;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canTransferMaterials(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_IN_PROCESS], true);
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [
            self::STATUS_MATERIAL_TRANSFERRED,
            self::STATUS_IN_PROCESS,
        ], true);
    }

    public function canClose(): bool
    {
        return in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_IN_PROCESS], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
        ], true);
    }

    public function getTotalServiceCharge(): float
    {
        return (float) $this->lines()->sum('total_service_charge');
    }

    public function getDisplayName(): string
    {
        return $this->order_number;
    }
}
